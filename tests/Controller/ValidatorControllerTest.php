<?php

namespace App\Tests\Controller;

use App\DataFixtures\ValidationsFixtures;
use App\Entity\Validation;
use App\Service\ValidatorArgumentsService;
use App\Test\WebTestCase;
use Liip\TestFixturesBundle\Test\FixturesTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests for ValidatorController class
 */
class ValidatorControllerTest extends WebTestCase
{
    use FixturesTrait;

    private $client;
    private $fs;
    private $em;

    private $valArgsService;

    public function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->fs = new Filesystem();

        $this->em = $this->getContainer()->get('doctrine')->getManager();

        $this->fixtures = $this->loadFixtures([
            ValidationsFixtures::class,
        ]);

        $this->valArgsService = new ValidatorArgumentsService(__DIR__ . '/../..');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->fs->remove(Validation::VALIDATIONS_DIRECTORY . '/test');
        $this->fs->remove(ValidationsFixtures::DIR_DATA_TEMP);

        $this->em->getConnection()->close();
    }

    /**
     * Get validation
     */
    public function testGetValidation()
    {
        $validation = $this->getReference('validation_no_args');

        $this->client->request(
            'GET',
            '/validator/validations/' . $validation->getUid(),
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
     * Get validation without uid parameter
     */
    public function testGetValidationWithoutUid()
    {
        $this->client->request(
            'GET',
            '/validator/validations/',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        // without the uid in url path, the request becomes trying to do a GET request at the address /validator/validations, which is not allowed
        $this->assertStatusCode(405, $this->client);
    }

    /**
     * Get validation with non existent uid
     */
    public function testGetValidationNotFound()
    {
        $this->client->request(
            'GET',
            '/validator/validations/no-record-for-this-uid',
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals('No record found for uid=no-record-for-this-uid', $json['error']);

    }

    /**
     * Testing upload dataset with correct params
     */
    public function testUploadDatasetCorrectParams()
    {
        $filename = ValidationsFixtures::FILENAME;
        $this->fs->copy(ValidationsFixtures::DIR_DATA . "/$filename", ValidationsFixtures::DIR_DATA_TEMP . "/$filename");

        $dataset = new UploadedFile(
            ValidationsFixtures::DIR_DATA_TEMP . "/$filename",
            $filename,
            'application/zip',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/validator/validations/',
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
     * Uploading correct file (zip), wrong parameter name
     */
    public function testUploadDatasetWrongParameterName()
    {
        $filename = ValidationsFixtures::FILENAME;
        $this->fs->copy(ValidationsFixtures::DIR_DATA . "/$filename", ValidationsFixtures::DIR_DATA_TEMP . "/$filename");

        $dataset = new UploadedFile(
            ValidationsFixtures::DIR_DATA_TEMP . "/$filename",
            $filename,
            'application/zip',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/validator/validations/',
            [],
            ['file' => $dataset],
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Argument [dataset] is missing', $json['error']);
    }

    /**
     * Uploading wrong file (not a compressed zip file)
     */
    public function testUploadDatasetWrongFileType()
    {
        $filename = 'testfile.txt';
        $this->fs->copy(ValidationsFixtures::DIR_DATA . "/$filename", ValidationsFixtures::DIR_DATA_TEMP . "/$filename");

        $dataset = new UploadedFile(
            ValidationsFixtures::DIR_DATA_TEMP . "/$filename",
            $filename,
            'text/plain',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/validator/validations/',
            [],
            ['dataset' => $dataset],
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Dataset must be in a compressed [.zip] file', $json['error']);
    }

    /**
     * Uploading no file at all
     */
    public function testUploadDatasetNoFile()
    {
        $this->client->request(
            'POST',
            '/validator/validations/',
            [],
            [],
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Argument [dataset] is missing', $json['error']);
    }

    /**
     * Deleting a validation
     */
    public function testDeleteValidation()
    {
        $validation = $this->getReference('validation_archived');

        $this->client->request(
            'DELETE',
            '/validator/validations/' . $validation->getUid(),
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(204, $this->client);
        $this->assertNull($this->em->getRepository(Validation::class)->findOneByUid($validation->getUid()));

        // trying to delete a validation that does not exist
        $this->client->request(
            'DELETE',
            '/validator/validations/' . $validation->getUid(),
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
    }

    /**
     * Updating arguments with correct parameters
     */
    public function testUpdateArguments()
    {
        $validation = $this->getReference('validation_no_args');

        $data = [
            'srs' => "EPSG:2154",
            'model' => "https://ocruze.github.io/fileserver/config/cnig_CC_2017.json",
            'normalize' => true,
        ];

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $data = $this->valArgsService->validate(json_encode($data));

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
     * Updating arguments but validation does not exist
     */
    public function testUpdateArgumentsValNotFound()
    {
        $data = [
            'srs' => "EPSG:2154",
            'model' => "https://ocruze.github.io/fileserver/config/cnig_CC_2017.json",
        ];

        $this->client->request(
            'PATCH',
            '/validator/validations/does-not-exist',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(404, $this->client);
        $this->assertEquals('No record found for uid=does-not-exist', $json['error']);
    }

    /**
     * Updating arguments with invalid json
     */
    public function testUpdateArgumentsInvalidJson()
    {
        $validation = $this->getReference('validation_no_args');
        $data = "{model = url}";

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals('Request body must be a valid JSON string', $json['error']);
    }

    /**
     * Updating arguments, no arguments provided
     */
    public function testUpdateArgumentsNoArguments()
    {
        $validation = $this->getReference('validation_no_args');
        $data = [];

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals("Request body must be a valid JSON string", $json['error']);
    }

    /**
     * Updating arguments, some required arguments are missing
     */
    public function testUpdateArgumentsMissingReqArguments()
    {
        $validation = $this->getReference('validation_no_args');
        $data = [
            'srs' => "EPSG:2154",
        ];

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
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
     * Updating arguments, with arguments that are unknown, deprecated or unauthorized
     */
    public function testUpdateArgumentsUnknownParameters()
    {
        $validation = $this->getReference('validation_no_args');

        $data = [
            'srs' => "EPSG:2154",
            'model' => "https://ocruze.github.io/fileserver/config/cnig_CC_2017.json",
            'foo' => 'bar',
        ];

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals("Arguments are invalid: 1 error(s) found, check details", $json['error']);
    }

    /**
     * Updating arguments with invalid boolean values
     */
    public function testUpdateArgumentsInvalidBoolean()
    {
        $validation = $this->getReference('validation_no_args');

        $data = [
            'srs' => "EPSG:2154",
            'model' => "https://ocruze.github.io/fileserver/config/cnig_CC_2017.json",
            'normalize' => 'vrai',
        ];

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals("Arguments are invalid: 1 error(s) found, check details", $json['error']);
    }

    /**
     * Updating arguments with invalid/unaccepted/unknown projection
     */
    public function testUpdateArgumentsUnknownProjection()
    {
        $validation = $this->getReference('validation_no_args');

        $data = [
            'srs' => "EPSG:9999",
            'model' => "https://ocruze.github.io/fileserver/config/cnig_CC_2017.json",
        ];

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals("Arguments are invalid: 1 error(s) found, check details", $json['error']);
    }

    /**
     * Updating arguments with invalid model url
     */
    public function testUpdateArgumentsInvalidModelUrl()
    {
        $validation = $this->getReference('validation_no_args');

        $data = [
            'srs' => "EPSG:2154",
            'model' => "ocruze.github.io/fileserver/config/cnig_CC_2017.json",
        ];

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(400, $this->client);
        $this->assertEquals("Arguments are invalid: 1 error(s) found, check details", $json['error']);
    }

    /**
     * Updating arguments but validation already archived
     */
    public function testUpdateArgumentsValArchived()
    {
        $validation = $this->getReference('validation_archived');

        $data = [
            'srs' => "EPSG:2154",
            'model' => "https://ocruze.github.io/fileserver/config/cnig_CC_2017.json",
        ];

        $this->client->request(
            'PATCH',
            '/validator/validations/' . $validation->getUid(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $json = \json_decode($response->getContent(), true);

        $this->assertStatusCode(403, $this->client);
        $this->assertEquals('Validation has been archived', $json['error']);
    }
}
