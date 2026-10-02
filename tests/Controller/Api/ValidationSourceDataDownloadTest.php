<?php

namespace App\Tests\Controller\Api;

use App\DataFixtures\ValidationsFixtures;
use App\Tests\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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
     * Download of the uploaded archive (written to the storage by the fixtures).
     */
    public function testDownload()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/files/source');

        $this->assertStatusCode(200, $this->client);
        $response = $this->client->getResponse();
        $this->assertEquals('application/zip', $response->headers->get('Content-Type'));
        $this->assertEquals(
            'attachment; filename='.$validation->getDatasetName().'-source.zip',
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringEqualsFile(
            $this->getTestDataDir().'/'.ValidationsFixtures::FILENAME_SUP_PM3,
            $this->client->getInternalResponse()->getContent()
        );
    }
}
