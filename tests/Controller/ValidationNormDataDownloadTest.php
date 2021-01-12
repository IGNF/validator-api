<?php

namespace App\Tests\Controller;

use App\DataFixtures\ValidationsFixtures;
use App\Entity\Validation;
use App\Test\WebTestCase;
use Liip\TestFixturesBundle\Test\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for download of normalized data
 */
class ValidationNormDataDownloadTest extends WebTestCase
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
     * Cases where there is no data to download
     */
    public function testDownloadNoData()
    {
        // $repo = $this->em->getRepository(Validation::class);
        // $validations = $repo->findAll();
        // (var_dump($validations));

        // validation not yet executed, no args
        $validation = $this->getReference('validation_no_args');

        $this->client->request(
            'GET',
            '/validator/validations/' . $validation->getUid() . '/download',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation hasn't been executed yet", $json['error']);

        // validation not yet executed, has args, execution pending
        $validation = $this->getReference('validation_with_args');

        $this->client->request(
            'GET',
            '/validator/validations/' . $validation->getUid() . '/download',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation hasn't been executed yet", $json['error']);

        // validation archived
        $validation = $this->getReference('validation_archived');

        $this->client->request(
            'GET',
            '/validator/validations/' . $validation->getUid() . '/download',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation has been archived", $json['error']);
    }

    /**
     * No validation corresponds to provided uid
     */
    public function testDownloadValNotFound()
    {
        $uid = "uid-validation-doesnt-exist";
        $this->client->request(
            'GET',
            '/validator/validations/' . $uid . '/download',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals("No record found for uid=$uid", $json['error']);
    }

    /**
     * Trying to download normalized data after execution of validations command
     */
    public function testDownload()
    {
        // this validation will run without any errors
        // running validations command twice because there are two validations pending
        static::ensureKernelShutdown();
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('app:validations');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);
        $this->assertEquals(0, $statusCode);

        $command = $application->find('app:validations');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);
        $this->assertEquals(0, $statusCode);

        // this one has failed
        $validation2 = $this->getReference('validation_with_args_2');

        $this->client->request(
            'GET',
            '/validator/validations/' . $validation2->getUid() . '/download',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation failed, no normalized data", $json['error']);

        // this one has succeeded
        $validation = $this->getReference('validation_with_args');

        $this->client->request(
            'GET',
            '/validator/validations/' . $validation->getUid() . '/download',
        );

        $response = $this->client->getResponse();
        $this->assertStatusCode(200, $this->client);

        $file = $response->getFile();
        $headers = $response->headers->all();

        $this->assertEquals('application/zip', $headers['content-type'][0]);
        $this->assertEquals($validation->getDatasetName() . '.zip', $file->getFilename());
        $this->assertEquals('zip', $file->getExtension());
    }
}
