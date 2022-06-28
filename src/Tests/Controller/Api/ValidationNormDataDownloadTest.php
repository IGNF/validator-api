<?php

namespace App\Tests\Controller\Api;

use App\Tests\DataFixtures\ValidationsFixtures;
use App\Tests\WebTestCase;
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

    public function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->fixtures = $this->loadFixtures([
            ValidationsFixtures::class,
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem();
        $fs->remove($this->getValidationsStorage()->getPath());
    }

    /**
     * Cases where there is no data to download
     */
    public function testDownloadNoData()
    {
        // validation not yet executed, no args
        $validation = $this->getReference('validation_no_args');

        $this->client->request(
            'GET',
            '/api/validations/' . $validation->getUid() . '/files/normalized',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation hasn't been executed yet", $json['message']);

        // validation not yet executed, has args, execution pending
        $validation = $this->getReference('validation_with_args');

        $this->client->request(
            'GET',
            '/api/validations/' . $validation->getUid() . '/files/normalized',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation hasn't been executed yet", $json['message']);

        // validation archived
        $validation = $this->getReference('validation_archived');

        $this->client->request(
            'GET',
            '/api/validations/' . $validation->getUid() . '/files/normalized',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation has been archived", $json['message']);
    }

    /**
     * No validation corresponds to provided uid
     */
    public function testDownloadValNotFound()
    {
        $uid = "uid-validation-doesnt-exist";
        $this->client->request(
            'GET',
            '/api/validations/' . $uid . '/files/normalized',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals("No record found for uid=$uid", $json['message']);
    }

    /**
     * Trying to download normalized data after execution of validations command
     */
    public function testDownload()
    {
        // running validations command twice because there are two validations pending
        static::ensureKernelShutdown();
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('ign-validator:validations:process-one');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);
        $this->assertEquals(0, $statusCode);

        $command = $application->find('ign-validator:validations:process-one');
        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([]);
        $this->assertEquals(0, $statusCode);

        // this one has failed
        $validation2 = $this->getReference('validation_with_bad_args');

        $this->client->request(
            'GET',
            '/api/validations/' . $validation2->getUid() . '/files/normalized',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation failed, no normalized data", $json['message']);

        // this one has succeeded
        $validation = $this->getReference('validation_with_args');

        $this->client->request(
            'GET',
            '/api/validations/' . $validation->getUid() . '/files/normalized',
        );

        $response = $this->client->getResponse();
        $this->assertStatusCode(200, $this->client);

        $file = $response->getFile();
        // TODO
        // expected: filename suffix should be -normalized.zip
        // actual: -normalized is not present in the suffix
        // var_dump($file);
        $headers = $response->headers->all();

        $this->assertEquals('application/zip', $headers['content-type'][0]);
        $this->assertEquals($validation->getDatasetName() . '.zip', $file->getFilename());
        $this->assertEquals('zip', $file->getExtension());
    }
}
