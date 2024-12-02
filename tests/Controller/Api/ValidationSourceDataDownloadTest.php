<?php

namespace App\Tests\Controller\Api;

use App\DataFixtures\ValidationsFixtures;
use App\Tests\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for download of source data.
 */
class ValidationSourceDataDownloadTest extends WebTestCase
{
    /**
     * @var AbstractDatabaseTool
     */
    private $databaseTool;

    /**
     * @var KernelBrowser
     */
    private $client;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->em = $this->getContainer()->get('doctrine')->getManager();

        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->fixtures = $this->databaseTool->loadFixtures([
            ValidationsFixtures::class,
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem();
        $fs->remove($this->getValidationsStorage()->getPath());

        $this->em->getConnection()->close();
    }

    /**
     * Cases where there is no data to download.
     */
    public function testDownloadNoData()
    {
        // validation archived
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_ARCHIVED);

        $this->client->request(
            'GET',
            '/api/validations/'.$validation->getUid().'/files/source',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals('Validation has been archived', $json['message']);
    }

    /**
     * No validation corresponds to provided uid.
     */
    public function testDownloadValNotFound()
    {
        $uid = 'uid-validation-doesnt-exist';
        $this->client->request(
            'GET',
            '/api/validations/'.$uid.'/files/source',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals("No record found for uid=$uid", $json['message']);
    }

    /**
     * Trying to download source data after execution of validations command.
     */
    public function testDownload()
    {
        $this->markTestSkipped('TODO : fix test');

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
        $validation2 = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_BAD_ARGS);

        $this->client->request(
            'GET',
            '/api/validations/'.$validation2->getUid().'/files/source',
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
        $this->assertEquals($validation2->getDatasetName().'.zip', $file->getFilename());
        $this->assertEquals('zip', $file->getExtension());

        // this one has succeeded
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->client->request(
            'GET',
            '/api/validations/'.$validation->getUid().'/files/source',
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
        $this->assertEquals($validation->getDatasetName().'.zip', $file->getFilename());
        $this->assertEquals('zip', $file->getExtension());
    }
}
