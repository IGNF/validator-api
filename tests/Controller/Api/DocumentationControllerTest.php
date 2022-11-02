<?php

namespace App\Tests\Controller\Api;

use App\Tests\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tests for ValidatorController class.
 */
class DocumentationControllerTest extends WebTestCase
{
    /**
     * Test openapi specification.
     */
    public function testSwagger()
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/validator-api.yml',
        );

        $this->assertResponseIsSuccessful();

        $response = $client->getResponse();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        $this->assertStringContainsString(
            "API permettant d'appeler [IGNF/validator](https://github.com/IGNF/validator)",
            $client->getInternalResponse()->getContent()
        );
    }

    /**
     * Get specification.
     */
    public function testSchemaValidatorArguments()
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/schema/validator-arguments.json',
        );

        $this->assertResponseIsSuccessful();

        $response = $client->getResponse();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        $this->assertStringContainsString(
            'Arguments et options de IGNF/validator',
            $client->getInternalResponse()->getContent()
        );
    }

    /**
     * Try to get a schema that does not exist.
     */
    public function testSchemaNotExists()
    {
        $schemaName = 'schema-does-not-exist';
        $client = static::createClient();
        $client->request(
            'GET',
            "/api/schema/$schemaName.json",
        );

        $this->assertStatusCode(404, $client);

        $response = $client->getResponse();
        $responseArray = json_decode($response->getContent(), true);

        $this->assertIsArray($responseArray);
        $this->assertArrayHasKey('message', $responseArray);
        $this->assertEquals("No schema found with name=$schemaName", $responseArray['message']);
    }
}
