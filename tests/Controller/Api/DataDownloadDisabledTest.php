<?php

namespace App\Tests\Controller\Api;

use App\DataFixtures\ValidationsFixtures;
use App\Tests\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests that source and normalized data cannot be downloaded when DATA_DOWNLOAD_ENABLED is off.
 */
class DataDownloadDisabledTest extends WebTestCase
{
    /**
     * @var KernelBrowser
     */
    private $client;

    public function setUp(): void
    {
        $_ENV['DATA_DOWNLOAD_ENABLED'] = $_SERVER['DATA_DOWNLOAD_ENABLED'] = '0';

        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->fixtures = $databaseTool->loadFixtures([
            ValidationsFixtures::class,
        ]);
    }

    public function tearDown(): void
    {
        $_ENV['DATA_DOWNLOAD_ENABLED'] = $_SERVER['DATA_DOWNLOAD_ENABLED'] = '1';

        parent::tearDown();
    }

    public static function filesProvider(): array
    {
        return [
            'source' => ['source'],
            'normalized' => ['normalized'],
        ];
    }

    #[DataProvider('filesProvider')]
    public function testDownloadIsDenied(string $files)
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/files/'.$files);

        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStatusCode(403, $this->client);
        $this->assertEquals('Data download is disabled', $json['message']);
    }

    /**
     * Unknown uids get the same answer, so the endpoint cannot be used to probe uids.
     */
    #[DataProvider('filesProvider')]
    public function testDownloadIsDeniedForUnknownUid(string $files)
    {
        $this->client->request('GET', '/api/validations/uid-validation-doesnt-exist/files/'.$files);

        $this->assertStatusCode(403, $this->client);
    }

    /**
     * Download endpoints are flagged as disabled in the OpenAPI specification.
     */
    public function testSwaggerFlagsDownloadPathsAsDisabled()
    {
        $this->client->request('GET', '/api/validator-api.yml');

        $this->assertResponseIsSuccessful();
        $specs = Yaml::parse($this->client->getResponse()->getContent());
        $this->assertArrayNotHasKey('x-disabled', $specs['paths']['/api/validations/{uid}/results.csv']['get']);

        $expected = Yaml::parseFile(static::getContainer()->getParameter('kernel.project_dir').'/docs/specs/validator-api.yml');
        foreach (['source', 'normalized'] as $files) {
            $path = "/api/validations/{uid}/files/$files";
            $operation = $specs['paths'][$path]['get'];
            $this->assertTrue($operation['deprecated']);
            $this->assertTrue($operation['x-disabled']);
            $this->assertStringStartsWith('[Désactivé] ', $operation['summary']);
            $this->assertStringContainsString('désactivée temporairement pour des raisons de sécurité', $operation['description']);

            // the documentation of the route is kept
            $this->assertEquals($expected['paths'][$path]['get']['responses'], $operation['responses']);
            unset($specs['paths'][$path], $expected['paths'][$path]);
        }

        // the rest of the specification is unchanged
        $this->assertEquals($expected, $specs);
    }
}
