<?php

namespace App\Tests\Controller\Api;

use App\DataFixtures\ValidationsFixtures;
use App\Entity\Validation;
use App\Service\ValidatorArgumentsService;
use App\Tests\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests for ValidatorController class.
 */
class ValidationControllerTest extends WebTestCase
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

    /**
     * @var ValidatorArgumentsService
     */
    private $valArgsService;

    public function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->em = $this->getContainer()->get('doctrine')->getManager();

        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->fixtures = $this->databaseTool->loadFixtures([
            ValidationsFixtures::class,
        ]);

        $this->valArgsService = $this->getContainer()->get(ValidatorArgumentsService::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem();
        $fs->remove($this->getValidationsStorage()->getPath());

        $this->em->getConnection()->close();
    }

    /**
     * Get validation.
     */
    public function testGetValidation()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        $this->client->request(
            'GET',
            '/api/validations/' . $validation->getUid(),
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(200, $this->client);
        $this->assertEquals($validation->getUid(), $json['uid']);
        $this->assertEquals($validation->getDatasetName(), $json['dataset_name']);
        $this->assertEquals($validation->getStatus(), $json['status']);
        $this->assertEquals($validation->getMessage(), $json['message']);
        $this->assertEquals($validation->getArguments(), $json['arguments']);
        $this->assertEquals($validation->getDateStart(), $json['date_start']);
        $this->assertEquals($validation->getDateFinish(), $json['date_finish']);
        $this->assertEquals($validation->getResults(), $json['results']);
    }

    /**
     * Get validation without uid parameter.
     */
    public function testGetValidationWithoutUid()
    {
        $this->client->request(
            'GET',
            '/api/validations/',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        // without the uid in url path, the request becomes trying to do a GET request at the address /api/validations, which is not allowed
        $this->assertStatusCode(405, $this->client);
    }

    /**
     * Get validation with non existent uid.
     */
    public function testGetValidationNotFound()
    {
        $this->client->request(
            'GET',
            '/api/validations/no-record-for-this-uid',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals('No record found for uid=no-record-for-this-uid', $json['message']);
    }

    /**
     * Testing upload dataset with correct params.
     */
    public function testUploadDatasetCorrectParams()
    {
        $filename = ValidationsFixtures::FILENAME_SUP_PM3;
        $dataset = $this->createFakeUpload(
            $filename,
            'application/zip'
        );

        $this->client->request(
            'POST',
            '/api/validations/',
            [],
            ['dataset' => $dataset],
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(201, $this->client);
        $this->assertEquals(24, \strlen($json['uid']));
        $this->assertSame(\str_replace('.zip', '', $filename), $json['dataset_name']);
        $this->assertEquals(Validation::STATUS_WAITING_ARGS, $json['status']);
        $this->assertNull($json['message']);
        $this->assertNull($json['arguments']);
        $this->assertNull($json['date_start']);
        $this->assertNull($json['date_finish']);
        $this->assertNull($json['results']);
    }

    /**
     * Uploading correct file (zip), wrong parameter name.
     */
    public function testUploadDatasetWrongParameterName()
    {
        $filename = ValidationsFixtures::FILENAME_SUP_PM3;
        $dataset = $this->createFakeUpload(
            $filename,
            'application/zip'
        );

        $this->client->request(
            'POST',
            '/api/validations/',
            [],
            ['file' => $dataset],
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Argument [dataset] is missing', $json['message']);
    }

    /**
     * Uploading wrong file (not a compressed zip file).
     */
    public function testUploadDatasetWrongFileType()
    {
        $filename = 'testfile.txt';
        $dataset = $this->createFakeUpload(
            $filename,
            'text/plain'
        );

        $this->client->request(
            'POST',
            '/api/validations/',
            [],
            ['dataset' => $dataset],
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Dataset must be in a compressed [.zip] file', $json['message']);
    }

    public static function invalidDatasetNameProvider(): array
    {
        return [
            'parent directory' => ['...zip'],
            'current directory' => ['..zip'],
            'empty name' => ['.zip'],
            'leading dash' => ['-r.zip'],
            'space' => ['my dataset.zip'],
            'accent' => ['données.zip'],
            'too long' => [str_repeat('a', 101).'.zip'],
        ];
    }

    /**
     * Uploading a zip whose name is not safe to be used in file paths.
     */
    #[DataProvider('invalidDatasetNameProvider')]
    public function testUploadDatasetInvalidName(string $clientName)
    {
        $sample = $this->createFakeUpload(ValidationsFixtures::FILENAME_SUP_PM3);
        $dataset = new UploadedFile($sample->getPathname(), $clientName, 'application/zip', null, true);

        $this->client->request('POST', '/api/validations/', [], ['dataset' => $dataset]);

        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStatusCode(400, $this->client);
        $this->assertStringStartsWith('Dataset filename is not valid', $json['message']);
        $this->assertEquals(0, $this->em->getRepository(Validation::class)->count([
            'status' => Validation::STATUS_WAITING_ARGS,
            'datasetName' => preg_replace('/\.zip$/', '', $clientName),
        ]));
    }

    /**
     * The .zip extension is removed whatever its case.
     */
    public function testUploadDatasetUpperCaseExtension()
    {
        $sample = $this->createFakeUpload(ValidationsFixtures::FILENAME_SUP_PM3);
        $dataset = new UploadedFile($sample->getPathname(), 'PLU_2026.v2.ZIP', 'application/zip', null, true);

        $this->client->request('POST', '/api/validations/', [], ['dataset' => $dataset]);

        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStatusCode(201, $this->client);
        $this->assertEquals('PLU_2026.v2', $json['dataset_name']);
    }

    /**
     * Uploading no file at all.
     */
    public function testUploadDatasetNoFile()
    {
        $this->client->request(
            'POST',
            '/api/validations/',
            [],
            [],
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Argument [dataset] is missing', $json['message']);
    }

    /**
     * Deleting a validation.
     */
    public function testDeleteValidation()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_ARCHIVED);

        $this->client->request(
            'DELETE',
            '/api/validations/' . $validation->getUid(),
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(204, $this->client);
        $this->assertNull($this->em->getRepository(Validation::class)->findOneByUid($validation->getUid()));

        // trying to delete a validation that does not exist
        $this->client->request(
            'DELETE',
            '/api/validations/' . $validation->getUid(),
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
    }

    /**
     * Deleting a validation removes its persisted files.
     */
    public function testDeleteValidationRemovesFiles()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);
        $storage = $this->getValidationsStorage();
        $uploadDirectory = $storage->getUploadDirectory($validation);
        $this->assertTrue($storage->getStorage()->directoryExists($uploadDirectory));

        $this->client->request('DELETE', '/api/validations/'.$validation->getUid());

        $this->assertStatusCode(204, $this->client);
        $this->assertFalse($storage->getStorage()->directoryExists($uploadDirectory));
    }

    /**
     * A validation being processed by a worker can't be updated or deleted.
     */
    public function testUpdateOrDeleteValidationBeingProcessed()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);
        $validation = $this->em->getRepository(Validation::class)->findOneByUid($validation->getUid());
        $validation->setStatus(Validation::STATUS_PROCESSING);
        $this->em->flush();

        $this->client->request(
            'PATCH',
            '/api/validations/'.$validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['srs' => 'EPSG:2154', 'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json'])
        );
        $this->assertStatusCode(409, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Validation is being processed, retry later', $json['message']);

        $this->client->request('DELETE', '/api/validations/'.$validation->getUid());
        $this->assertStatusCode(409, $this->client);

        $this->em->clear();
        $validation = $this->em->getRepository(Validation::class)->findOneByUid($validation->getUid());
        $this->assertEquals(Validation::STATUS_PROCESSING, $validation->getStatus());
    }

    /**
     * Updating arguments with correct parameters.
     */
    public function testUpdateArguments()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        $data = [
            'srs' => 'EPSG:2154',
            'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json',
            'normalize' => true,
        ];

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $data = $this->valArgsService->validate(json_encode($data));
        unset($data['delete-data']);

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);
        $arguments = $json['arguments'];

        $this->assertStatusCode(200, $this->client);
        $this->assertSame($data, $arguments);
        $this->assertEquals(Validation::STATUS_PENDING, $json['status']);
        $this->assertNull($json['message']);
        $this->assertNull($json['date_start']);
        $this->assertNull($json['date_finish']);
        $this->assertNull($json['results']);

        foreach ($data as $argName => $value) {
            $this->assertSame($data[$argName], $arguments[$argName]);
        }
    }

    /**
     * Updating arguments but validation does not exist.
     */
    public function testUpdateArgumentsValNotFound()
    {
        $data = [
            'srs' => 'EPSG:2154',
            'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json',
        ];

        $this->client->request(
            'PATCH',
            '/api/validations/does-not-exist',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals('No record found for uid=does-not-exist', $json['message']);
    }

    /**
     * Updating arguments with invalid json.
     */
    public function testUpdateArgumentsInvalidJson()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);
        $data = '{model = url}';

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Request body must be a valid JSON string', $json['message']);
    }

    /**
     * Updating arguments, no arguments provided.
     */
    public function testUpdateArgumentsNoArguments()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);
        $data = [];

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Request body must be a valid JSON string', $json['message']);
    }

    /**
     * Updating arguments, some required arguments are missing.
     */
    public function testUpdateArgumentsMissingReqArguments()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);
        $data = [
            'srs' => 'EPSG:2154',
        ];

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        // $reqArgs = $this->valArgsService->getRequiredArgs();
        // $expError = sprintf('Arguments [%s] are required', \implode(', ', \array_keys($reqArgs)));

        $this->assertStatusCode(400, $this->client);
        // $this->assertEquals($expError, $json['error']);
    }

    /**
     * Updating arguments, with arguments that are unknown, deprecated or unauthorized.
     */
    public function testUpdateArgumentsUnknownParameters()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        $data = [
            'srs' => 'EPSG:2154',
            'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json',
            'foo' => 'bar',
        ];

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Invalid arguments, check details', $json['message']);
    }

    /**
     * Updating arguments with invalid boolean values.
     */
    public function testUpdateArgumentsInvalidBoolean()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        $data = [
            'srs' => 'EPSG:2154',
            'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json',
            'normalize' => 'vrai',
        ];

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Invalid arguments, check details', $json['message']);
    }

    /**
     * Updating arguments with invalid/unaccepted/unknown projection.
     */
    public function testUpdateArgumentsUnknownProjection()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        $data = [
            'srs' => 'EPSG:9999',
            'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json',
        ];

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Invalid arguments, check details', $json['message']);
    }

    /**
     * Updating arguments with invalid model url.
     */
    public function testUpdateArgumentsInvalidModelUrl()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        $data = [
            'srs' => 'EPSG:2154',
            'model' => 'geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json',
        ];

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Invalid arguments, check details', $json['message']);
    }

    public static function forbiddenModelUrlProvider(): array
    {
        return [
            'http' => ['http://www.geoportail-urbanisme.gouv.fr/standard/cnig_PLU_2017.json'],
            'local file' => ['file:///etc/passwd'],
            'cloud metadata' => ['https://169.254.169.254/latest/meta-data/'],
            'internal host' => ['https://localhost/model.json'],
            'userinfo trick' => ['https://ignf.github.io@evil.example/model.json'],
            'backslash trick' => ['https://evil.example\\@ignf.github.io/model.json'],
            'port' => ['https://ignf.github.io:8443/model.json'],
            'suffix trick' => ['https://evilignf.github.io/model.json'],
            'allowed host as subdomain' => ['https://ignf.github.io.evil.example/model.json'],
        ];
    }

    /**
     * The model url must be https on an allowed host (SSRF protection).
     */
    #[DataProvider('forbiddenModelUrlProvider')]
    public function testUpdateArgumentsForbiddenModelUrl(string $model)
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        $this->client->request(
            'PATCH',
            '/api/validations/'.$validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['srs' => 'EPSG:2154', 'model' => $model])
        );

        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Invalid arguments, check details', $json['message']);
        $this->assertEquals('model', $json['details'][0]['name']);
    }

    /**
     * Subdomains of the allowed hosts are allowed.
     */
    public function testUpdateArgumentsAllowedModelUrl()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS);

        foreach ([
            'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_PLU_2017.json',
            'https://IGNF.github.io/validator/validator-plugin-cnig/src/test/resources/config/cnig_SUP_PM3_2016.json',
        ] as $model) {
            $this->client->request(
                'PATCH',
                '/api/validations/'.$validation->getUid(),
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['srs' => 'EPSG:2154', 'model' => $model])
            );
            $this->assertStatusCode(200, $this->client);
        }
    }

    /**
     * Updating arguments but validation already archived.
     */
    public function testUpdateArgumentsValArchived()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_ARCHIVED);

        $data = [
            'srs' => 'EPSG:2154',
            'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json',
        ];

        $this->client->request(
            'PATCH',
            '/api/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals('Validation has been archived', $json['message']);
    }
}
