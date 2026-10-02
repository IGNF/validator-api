<?php

namespace App\Tests\Controller\Api;

use App\DataFixtures\ValidationsFixtures;
use App\Tests\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests the limitation of the number of validations created or updated per client IP.
 */
class RateLimitTest extends WebTestCase
{
    /**
     * @var KernelBrowser
     */
    private $client;

    public function setUp(): void
    {
        $_ENV['VALIDATION_RATE_LIMIT'] = $_SERVER['VALIDATION_RATE_LIMIT'] = '2';

        static::ensureKernelShutdown();
        $this->client = static::createClient();
        // counters of previous tests
        static::getContainer()->get('limiter.validation')->create('127.0.0.1')->reset();

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->fixtures = $databaseTool->loadFixtures([
            ValidationsFixtures::class,
        ]);
    }

    public function tearDown(): void
    {
        $_ENV['VALIDATION_RATE_LIMIT'] = $_SERVER['VALIDATION_RATE_LIMIT'] = '1000';

        parent::tearDown();

        (new Filesystem())->remove($this->getValidationsStorage()->getPath());
    }

    public function testTooManyRequests()
    {
        $uid = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS)->getUid();
        $patch = fn () => $this->client->request(
            'PATCH',
            '/api/validations/'.$uid,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['srs' => 'EPSG:2154', 'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json'])
        );

        $patch();
        $this->assertStatusCode(200, $this->client);
        $patch();
        $this->assertStatusCode(200, $this->client);

        $patch();
        $this->assertStatusCode(429, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringStartsWith('Too many requests, retry after ', $json['message']);

        // uploads share the same limit
        $this->client->request('POST', '/api/validations/', [], ['dataset' => $this->createFakeUpload(ValidationsFixtures::FILENAME_SUP_PM3)]);
        $this->assertStatusCode(429, $this->client);
    }
}
