<?php

namespace App\Tests\Controller\Api;

use App\Tests\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tests for ValidatorController class
 */
class DocumentationControllerTest extends WebTestCase
{

    /**
     * Get swagger ui page.
     */
    public function testIndex()
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/',
        );

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#swagger-ui-container', "Chargement...");
    }

    /**
     * Get specification
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
     * Get specification
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
            "Arguments et options de IGNF/validator",
            $client->getInternalResponse()->getContent()
        );
    }

}
