<?php

namespace App\Tests\Command\Validations;

use App\Entity\Validation;
use App\DataFixtures\ValidationsFixtures;
use App\Tests\WebTestCase;
use Liip\TestFixturesBundle\Test\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for ProcessOneCommand class
 */
class CleanupCommandTest extends WebTestCase
{
    use FixturesTrait;

    private $client;
    private $fs;
    private $em;

    public function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->fs = new Filesystem();

        $this->em = $this->getContainer()->get('doctrine')->getManager();

        $this->fixtures = $this->loadFixtures([
            ValidationsFixtures::class,
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->fs->remove($this->getValidationsStorage()->getPath());

        $this->em->getConnection()->close();
    }

    /**
     * Testing execution of the command
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
            '--max-age'=>'PT1S'
        ]);

        $this->assertEquals(0, $statusCode);

        /** @var array<Validation> $validations */
        $validations = $this->em->getRepository(Validation::class)->findAll();
        foreach ( $validations as $validation ){
            $this->assertEquals(Validation::STATUS_ARCHIVED,$validation->getStatus());
            $validationDirectory = $this->getValidationsStorage()->getDirectory($validation);
            $this->assertFalse(file_exists($validationDirectory));
        }
    }
}
