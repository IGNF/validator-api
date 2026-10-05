<?php

namespace App\Tests\Command\Validations;

use App\Command\Validations\CleanupCommand;
use App\DataFixtures\ValidationsFixtures;
use App\Entity\Validation;
use App\Repository\ValidationRepository;
use App\Tests\WebTestCase;
use App\Validation\ValidationManager;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for ProcessOneCommand class.
 */
class CleanupCommandTest extends WebTestCase
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
        $this->databaseTool->loadFixtures([
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
     * Testing execution of the command.
     */
    public function testCleanupOneSecond()
    {
        static::ensureKernelShutdown();

        // wait for 2 seconds
        sleep(2);

        // archive all older than 1 second
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('ign-validator:validations:cleanup');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([
            '--max-age' => 'PT1S',
        ]);

        $this->assertEquals(0, $statusCode);

        /** @var array<Validation> $validations */
        $validations = $this->em->getRepository(Validation::class)->findAll();
        foreach ($validations as $validation) {
            $this->assertEquals(Validation::STATUS_ARCHIVED, $validation->getStatus());
            $validationDirectory = $this->getValidationsStorage()->getDirectory($validation);
            $this->assertFalse(file_exists($validationDirectory));
        }
    }

    /**
     * Validations being processed by a worker are not archived.
     */
    public function testCleanupIgnoresProcessing()
    {
        $validation = $this->em->getRepository(Validation::class)->findOneBy(['status' => Validation::STATUS_PENDING]);
        $validation->setStatus(Validation::STATUS_PROCESSING);
        $this->em->flush();
        $uid = $validation->getUid();

        static::ensureKernelShutdown();
        sleep(2);

        $application = new Application(static::createKernel());
        $commandTester = new CommandTester($application->find('ign-validator:validations:cleanup'));
        $this->assertEquals(0, $commandTester->execute(['--max-age' => 'PT1S']));

        $this->em->clear();
        $validation = $this->em->getRepository(Validation::class)->findOneByUid($uid);
        $this->assertEquals(Validation::STATUS_PROCESSING, $validation->getStatus());
    }

    /**
     * Validations still processing after processing-timeout (worker killed...) are marked as failed.
     */
    public function testCleanupMarksInterruptedValidations()
    {
        $validation = $this->em->getRepository(Validation::class)->findOneBy(['status' => Validation::STATUS_PENDING]);
        $validation->setStatus(Validation::STATUS_PROCESSING);
        $validation->setDateStart(new \DateTime('-2 hours'));
        $this->em->flush();
        $uid = $validation->getUid();

        static::ensureKernelShutdown();
        $application = new Application(static::createKernel());
        $commandTester = new CommandTester($application->find('ign-validator:validations:cleanup'));
        $this->assertEquals(0, $commandTester->execute(['--processing-timeout' => 'PT1H']));
        $this->assertStringContainsString('1 interrupted validation(s) marked as failed.', $commandTester->getDisplay());

        $this->em->clear();
        $validation = $this->em->getRepository(Validation::class)->findOneByUid($uid);
        $this->assertEquals(Validation::STATUS_ERROR, $validation->getStatus());
        $this->assertEquals('Validation failed (processing interrupted)', $validation->getMessage());
    }

    /**
     * The default processing timeout is longer than the validator-cli.jar timeout (VALIDATOR_TIMEOUT).
     */
    public function testDefaultProcessingTimeout()
    {
        $command = fn (int $timeout) => new CleanupCommand(
            $this->createStub(ValidationRepository::class),
            $this->createStub(ValidationManager::class),
            $this->createStub(LoggerInterface::class),
            $timeout
        );

        // at least 1 hour
        $this->assertEquals('PT3600S', $command(600)->getDefaultProcessingTimeout());
        // timeout + 10 minutes
        $this->assertEquals('PT7800S', $command(7200)->getDefaultProcessingTimeout());
    }

    /**
     * A validation running for less than the default processing timeout is not marked as failed.
     */
    public function testCleanupKeepsLongValidationsByDefault()
    {
        $validation = $this->em->getRepository(Validation::class)->findOneBy(['status' => Validation::STATUS_PENDING]);
        $validation->setStatus(Validation::STATUS_PROCESSING);
        // VALIDATOR_TIMEOUT=1800 : default processing timeout of 1 hour
        $validation->setDateStart(new \DateTime('-40 minutes'));
        $this->em->flush();
        $uid = $validation->getUid();

        static::ensureKernelShutdown();
        $application = new Application(static::createKernel());
        $commandTester = new CommandTester($application->find('ign-validator:validations:cleanup'));
        $this->assertEquals(0, $commandTester->execute([]));
        $this->assertStringContainsString('0 interrupted validation(s) marked as failed.', $commandTester->getDisplay());

        $this->em->clear();
        $this->assertEquals(Validation::STATUS_PROCESSING, $this->em->getRepository(Validation::class)->findOneByUid($uid)->getStatus());
    }
}
