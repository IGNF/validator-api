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
     * Testing execution of the command.
     */
    public function testExecute()
    {
        $repo = $this->em->getRepository(Validation::class);

        // this validation will run without any errors
        static::ensureKernelShutdown();
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('ign-validator:validations:process-one');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertEquals(0, $statusCode);

        $valWithArgs = $this->getReference('validation_with_args');
        $validation = $repo->findOneByUid($valWithArgs->getUid());

        $this->assertEquals('', $validation->getMessage());
        $this->assertEquals(Validation::STATUS_FINISHED, $validation->getStatus());
        $this->assertNotNull($validation->getDateStart());
        $this->assertNotNull($validation->getDateFinish());
        $this->assertNotNull($validation->getResults());

        // this one will fail, the model_url argument is wrong and will raise a Java runtime exception which will mark the Symfony process as failed
        $command = $application->find('ign-validator:validations:process-one');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertEquals(0, $statusCode);

        $valWithBadArgs = $this->getReference('validation_with_bad_args');
        $validation = $repo->findOneByUid($valWithBadArgs->getUid());

        $this->assertNotEquals('', $validation->getMessage());
        $this->assertEquals(Validation::STATUS_ERROR, $validation->getStatus());
        $this->assertNotNull($validation->getDateStart());
        $this->assertNotNull($validation->getDateFinish());
        $this->assertNull($validation->getResults()); // TODO fails intermittently ¯\_(ツ)_/¯

        // this one will fail because the zip archive is invalid
        $command = $application->find('ign-validator:validations:process-one');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertEquals(0, $statusCode);

        $valInvalidRegex = $this->getReference('validation_invalid_regex');
        $validation = $repo->findOneByUid($valInvalidRegex->getUid());

        $this->assertEquals('Zip archive pre-validation failed', $validation->getMessage());
        $this->assertEquals(Validation::STATUS_ERROR, $validation->getStatus());
        $this->assertNotNull($validation->getDateStart());
        $this->assertNotNull($validation->getDateFinish());
        $this->assertEquals(2, count($validation->getResults()));

        // no validation pending, the command should exit right away
        $command = $application->find('ign-validator:validations:process-one');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertEquals(0, $statusCode);
    }
}
