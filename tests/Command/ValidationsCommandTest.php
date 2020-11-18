<?php

use App\DataFixtures\ValidationsFixtures;
use App\Entity\Validation;
use App\Test\WebTestCase;
use Liip\TestFixturesBundle\Test\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for ValidationsCommand class
 */
class ValidationsCommandTest extends WebTestCase
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

        $this->fs->remove(Validation::VALIDATIONS_DIRECTORY . '/test');
        $this->fs->remove(ValidationsFixtures::DIR_DATA_TEMP);

        $this->em->getConnection()->close();
    }

    /**
     * Testing execution of the command app:valildations
     */
    public function testExecute()
    {
        $repo = $this->em->getRepository(Validation::class);

        // this validation will run without any errors
        static::ensureKernelShutdown();
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('app:validations');
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
        $command = $application->find('app:validations');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertEquals(0, $statusCode);

        $valWithArgs2 = $this->getReference('validation_with_args_2');
        $validation = $repo->findOneByUid($valWithArgs2->getUid());

        $this->assertNotEquals('', $validation->getMessage());
        $this->assertEquals(Validation::STATUS_ERROR, $validation->getStatus());
        $this->assertNotNull($validation->getDateStart());
        $this->assertNotNull($validation->getDateFinish());
        $this->assertNull($validation->getResults());

        // no validation pending, the command should exit right away
        $command = $application->find('app:validations');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);

        $this->assertEquals(0, $statusCode);
    }
}
