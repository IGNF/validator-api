<?php

namespace App\Tests\Controller\Api;

use App\DataFixtures\ValidationsFixtures;
use App\Tests\WebTestCase;
use App\Entity\Validation;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for download of normalized data.
 */
class ValidationNormDataDownloadTest extends WebTestCase
{
    /**
     * @var AbstractDatabaseTool
     */
    private $databaseTool;

    /**
     * @var KernelBrowser
     */
    private $client;

    public function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();

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
    }

    /**
     * Cases where there is no data to download.
     */
    public function testDownloadNoData()
    {
        // validation not yet executed, no args
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        $this->client->request(
            'GET',
            '/api/validations/'.$validation->getUid().'/files/normalized',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation hasn't been executed yet", $json['message']);

        // validation not yet executed, has args, execution pending
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->client->request(
            'GET',
            '/api/validations/'.$validation->getUid().'/files/normalized',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals("Validation hasn't been executed yet", $json['message']);

        // validation archived
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_ARCHIVED);

        $this->client->request(
            'GET',
            '/api/validations/'.$validation->getUid().'/files/normalized',
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
            '/api/validations/'.$uid.'/files/normalized',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals("No record found for uid=$uid", $json['message']);
    }

    /**
     * Download of the normalized data of a finished validation.
     */
    public function testDownload()
    {
        $uid = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS)->getUid();
        $em = static::getContainer()->get('doctrine')->getManager();
        $validation = $em->getRepository(Validation::class)->findOneByUid($uid);
        $validation->setStatus(Validation::STATUS_FINISHED);
        $em->flush();

        $storage = $this->getValidationsStorage();
        $storage->getStorage()->write(
            $storage->getOutputDirectory($validation).$validation->getDatasetName().'.zip',
            'normalized-zip-content'
        );

        $this->client->request('GET', '/api/validations/'.$uid.'/files/normalized');

        $this->assertStatusCode(200, $this->client);
        $response = $this->client->getResponse();
        $this->assertEquals('application/zip', $response->headers->get('Content-Type'));
        $this->assertEquals('22', $response->headers->get('Content-Length'));
        $this->assertEquals(
            'attachment; filename='.$validation->getDatasetName().'-normalized.zip',
            $response->headers->get('Content-Disposition')
        );
        $this->assertEquals('normalized-zip-content', $this->client->getInternalResponse()->getContent());
    }
}
