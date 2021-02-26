<?php

namespace App\Tests\Controller;

use App\Entity\Validation;
use App\Tests\DataFixtures\ValidationsFixtures;
use App\Tests\WebTestCase;
use Liip\TestFixturesBundle\Test\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for download of source data
 */
class ValidationSourceDataDownloadTest extends WebTestCase
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
        // validation archived
        $validation = $this->getReference('validation_archived');

        $this->client->request(
            'GET',
            '/validator/validations/' . $validation->getUid() . '/files/source',
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
            '/validator/validations/' . $uid . '/files/source',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals("No record found for uid=$uid", $json['message']);
    }

    /**
     * Trying to download source data after execution of validations command
     */
    public function testDownload()
    {
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
            '/validator/validations/' . $validation2->getUid() . '/files/source',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(200, $this->client);

        $file = $response->getFile();
        // TODO
        // expected: filename suffix should be -source.zip
        // actual: -source is not present in the suffix
        // var_dump($file);
        $headers = $response->headers->all();

        $this->assertEquals('application/zip', $headers['content-type'][0]);
        $this->assertEquals($validation2->getDatasetName() . '-source.zip', $file->getFilename());
        $this->assertEquals('zip', $file->getExtension());

        // this one has succeeded
        $validation = $this->getReference('validation_with_args');

        $this->client->request(
            'GET',
            '/validator/validations/' . $validation->getUid() . '/files/source',
        );

        $response = $this->client->getResponse();
        $this->assertStatusCode(200, $this->client);

        $file = $response->getFile();
        // TODO
        // expected: filename suffix should be -source.zip
        // actual: -source is not present in the suffix
        // var_dump($file);
        $headers = $response->headers->all();

        $this->assertEquals('application/zip', $headers['content-type'][0]);
        $this->assertEquals($validation->getDatasetName() . '-source.zip', $file->getFilename());
        $this->assertEquals('zip', $file->getExtension());
    }
}
