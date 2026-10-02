<?php

namespace App\Tests\Command\Validations;

use App\DataFixtures\ValidationsFixtures;
use App\Entity\Validation;
use App\Tests\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for ProcessOneCommand class.
 */
class ProcessOneCommandTest extends WebTestCase
{
    /**
     * @var AbstractDatabaseTool
     */
    private $databaseTool;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function setUp(): void
    {
        parent::setUp();

        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->fixtures = $this->databaseTool->loadFixtures([
            ValidationsFixtures::class,
        ]);

        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem();
        $fs->remove($this->getValidationsStorage()->getPath());

        $this->em->getConnection()->close();
    }

    /**
     * Valid dataset : validator-cli.jar is executed and the results are saved.
     */
    public function testExecuteValid()
    {
        $validation = $this->processOnly(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->assertNull($validation->getMessage());
        $this->assertEquals(Validation::STATUS_FINISHED, $validation->getStatus());
        $this->assertNotNull($validation->getDateStart());
        $this->assertNotNull($validation->getDateFinish());
        $this->assertNotNull($validation->getResults());
        // logs are saved to the storage
        $storage = $this->getValidationsStorage();
        $this->assertTrue($storage->getStorage()->fileExists($storage->getOutputDirectory($validation).'validator-debug.log'));
    }

    /**
     * Wrong model url : validator-cli.jar fails, the raw error is not exposed.
     */
    public function testExecuteValidatorFailure()
    {
        $validation = $this->processOnly(ValidationsFixtures::VALIDATION_WITH_BAD_ARGS);

        $this->assertStringStartsWith('Validation failed (exit code ', $validation->getMessage());
        $this->assertEquals(Validation::STATUS_ERROR, $validation->getStatus());
        $this->assertNotNull($validation->getDateStart());
        $this->assertNotNull($validation->getDateFinish());
        $this->assertNull($validation->getResults());
    }

    /**
     * Invalid file names in the zip : the zip pre-validation fails.
     */
    public function testExecuteInvalidZip()
    {
        $validation = $this->processOnly(ValidationsFixtures::VALIDATION_INVALID_REGEX);

        $this->assertEquals('Zip archive pre-validation failed', $validation->getMessage());
        $this->assertEquals(Validation::STATUS_ERROR, $validation->getStatus());
        $this->assertNotNull($validation->getDateFinish());
        $this->assertEquals(2, count($validation->getResults()));
    }

    /**
     * No validation pending : the command exits right away.
     */
    public function testExecuteNothingPending()
    {
        foreach ($this->em->getRepository(Validation::class)->findBy(['status' => Validation::STATUS_PENDING]) as $validation) {
            $validation->setStatus(Validation::STATUS_WAITING_ARGS);
        }
        $this->em->flush();

        $this->assertEquals(0, $this->executeCommand());
    }

    /**
     * Processes the given fixture only (the other pending validations are put on hold
     * as popNextPending order is undefined for validations created in the same second).
     */
    private function processOnly(string $fixture): Validation
    {
        $uid = $this->getValidationFixture($fixture)->getUid();
        foreach ($this->em->getRepository(Validation::class)->findBy(['status' => Validation::STATUS_PENDING]) as $validation) {
            if ($validation->getUid() !== $uid) {
                $validation->setStatus(Validation::STATUS_WAITING_ARGS);
            }
        }
        $this->em->flush();

        $this->assertEquals(0, $this->executeCommand());

        $this->em->clear();

        return $this->em->getRepository(Validation::class)->findOneByUid($uid);
    }

    private function executeCommand(): int
    {
        static::ensureKernelShutdown();
        $application = new Application(static::createKernel());
        $commandTester = new CommandTester($application->find('ign-validator:validations:process-one'));

        return $commandTester->execute([]);
    }
}
