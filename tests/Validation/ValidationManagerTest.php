<?php

namespace App\Tests\Validation;

use App\Entity\Validation;
use App\Exception\ValidationProcessException;
use App\Repository\ValidationRepository;
use App\Validation\ValidationManager;
use App\Validation\ValidationWorkspace;
use App\Validation\ValidatorCLI;
use App\Validation\ZipArchiveValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Tests the processing workflow of ValidationManager (error messages and cleanup).
 */
#[AllowMockObjectsWithoutExpectations]
class ValidationManagerTest extends TestCase
{
    private ValidationWorkspace&MockObject $workspace;
    private ValidatorCLI&MockObject $validatorCli;
    private ValidationRepository&MockObject $repository;
    private ValidationManager $manager;
    private Validation $validation;

    public function setUp(): void
    {
        $this->validation = (new Validation())->setDatasetName('dataset');
        $this->validation->setStatus(Validation::STATUS_PROCESSING);

        $this->repository = $this->createMock(ValidationRepository::class);
        $this->repository->method('popNextPending')->willReturn($this->validation);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->repository);

        $zipArchiveValidator = $this->createStub(ZipArchiveValidator::class);
        $zipArchiveValidator->method('validate')->willReturn([]);

        $this->workspace = $this->createMock(ValidationWorkspace::class);
        $this->validatorCli = $this->createMock(ValidatorCLI::class);

        $this->manager = new ValidationManager(
            $em,
            $this->workspace,
            $this->validatorCli,
            $zipArchiveValidator,
            new NullLogger(),
            $this->repository
        );
    }

    public function testProcessSuccess()
    {
        $this->workspace->expects($this->once())->method('saveNormalizedData');
        $this->workspace->expects($this->once())->method('saveLog');
        $this->workspace->expects($this->once())->method('removeLocalDirectory');
        $this->workspace->expects($this->never())->method('removePersistedFiles');

        $this->manager->processOne();

        $this->assertEquals(Validation::STATUS_FINISHED, $this->validation->getStatus());
        $this->assertNotNull($this->validation->getDateFinish());
    }

    public static function errorProvider(): array
    {
        $process = new Process(['false']);
        $process->run();

        return [
            'validator-cli failure (command line and output are hidden)' => [
                new ProcessFailedException($process),
                'Validation failed (exit code 1)',
            ],
            'validator-cli timeout (VALIDATOR_TIMEOUT)' => [
                new ProcessTimedOutException((new Process(['sleep', '1']))->setTimeout(1800), ProcessTimedOutException::TYPE_GENERAL),
                'Validation failed (not completed after 1800 seconds)',
            ],
            'internal error (raw message is hidden)' => [
                new \RuntimeException('Unable to write /opt/validator-api/var/data/secret'),
                'Validation failed (internal error)',
            ],
            'error designed for the user' => [
                new ValidationProcessException('Zip decompression failed'),
                'Zip decompression failed',
            ],
        ];
    }

    /**
     * Raw error messages are not exposed and logs are saved / local files removed on failure.
     */
    #[DataProvider('errorProvider')]
    public function testProcessError(\Throwable $error, string $expectedMessage)
    {
        $this->validatorCli->method('process')->willThrowException($error);
        $this->workspace->expects($this->never())->method('saveNormalizedData');
        $this->workspace->expects($this->once())->method('saveLog');
        $this->workspace->expects($this->once())->method('removeLocalDirectory');

        $this->manager->processOne();

        $this->assertEquals(Validation::STATUS_ERROR, $this->validation->getStatus());
        $this->assertEquals($expectedMessage, $this->validation->getMessage());
    }

    /**
     * delete-data : files are removed whatever the result.
     */
    public function testProcessDeleteData()
    {
        $this->validation->setDeleteData(true);
        $this->workspace->expects($this->once())->method('removePersistedFiles');
        $this->repository->expects($this->once())->method('dropSchema');

        $this->manager->processOne();

        $this->assertEquals(Validation::STATUS_ARCHIVED, $this->validation->getStatus());
    }

    /**
     * delete-data on failure : files are removed but the error status is kept.
     */
    public function testProcessDeleteDataOnError()
    {
        $this->validation->setDeleteData(true);
        $this->validatorCli->method('process')->willThrowException(new \RuntimeException('boom'));
        $this->workspace->expects($this->once())->method('removePersistedFiles');

        $this->manager->processOne();

        $this->assertEquals(Validation::STATUS_ERROR, $this->validation->getStatus());
    }

    /**
     * A cleanup failure doesn't prevent the status from being saved.
     */
    public function testProcessCleanupFailure()
    {
        $this->workspace->method('saveLog')->willThrowException(new \RuntimeException('storage down'));
        $this->workspace->method('removeLocalDirectory')->willThrowException(new \RuntimeException('disk error'));

        $this->manager->processOne();

        $this->assertEquals(Validation::STATUS_FINISHED, $this->validation->getStatus());
    }

    /**
     * SIGTERM while processing : validator-cli.jar is stopped and the validation is restarted later.
     */
    public function testCancelProcessing()
    {
        $this->validatorCli->method('process')->willReturnCallback(function () {
            $this->manager->cancelProcessing();
        });
        $this->validatorCli->expects($this->once())->method('stop');
        $this->workspace->expects($this->atLeastOnce())->method('removeLocalDirectory');

        $this->manager->processOne();

        // processOne normally doesn't return (the console application exits after the signal handler)
        $this->assertNotNull($this->validation->getStatus());
    }

    /**
     * Without validation in progress, SIGTERM has no effect.
     */
    public function testCancelProcessingWithoutValidation()
    {
        $this->validatorCli->expects($this->never())->method('stop');

        $this->manager->cancelProcessing();
    }
}
