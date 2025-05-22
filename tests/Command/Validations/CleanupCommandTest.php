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
}
