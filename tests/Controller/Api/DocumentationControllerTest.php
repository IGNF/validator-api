<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\DocumentationController;
use App\Tests\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for ValidatorController class.
 */
class DocumentationControllerTest extends WebTestCase
{
    /**
     * /api redirects to the swagger displayed by the demo client.
     */
    public function testApiRootRedirectsToSwagger()
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', '/api');

        $this->assertResponseRedirects('/#/api');
    }

    /**
     * Test openapi specification.
     */
    public function testSwagger()
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/validator-api.yml',
        );

        $this->assertResponseIsSuccessful();

        $this->assertStringContainsString(
            "API permettant d'appeler [IGNF/validator](https://github.com/IGNF/validator)",
            $client->getInternalResponse()->getContent()
        );
        // APP_VERSION=dev (.env)
        $specs = Yaml::parse($client->getResponse()->getContent());
        $this->assertSame('dev', $specs['info']['version']);
    }

    /**
     * The version of the specification is the version of the release (git tag of the docker image).
     */
    public function testSwaggerVersion()
    {
        $projectDir = dirname(__DIR__, 3);
        foreach (['v0.7.16' => '0.7.16', '0.7.16-rc.1' => '0.7.16-rc.1', 'pr-12' => 'pr-12'] as $appVersion => $expected) {
            $controller = new DocumentationController($projectDir, true, $appVersion);
            $specs = Yaml::parse($controller->swagger()->getContent());
            $this->assertSame($expected, $specs['info']['version']);
        }
    }

    /**
     * Every operation documented in the specification matches an existing route
     * (prevents documenting routes that have been removed).
     */
    public function testSwaggerOperationsMatchRoutes()
    {
        self::ensureKernelShutdown();
        static::bootKernel();
        $router = static::getContainer()->get(RouterInterface::class);
        $specs = Yaml::parseFile(static::getContainer()->getParameter('kernel.project_dir').'/docs/specs/validator-api.yml');

        foreach ($specs['paths'] as $path => $operations) {
            // replace path parameters ({uid}, {schemaName}...) by a sample value
            $url = preg_replace('/\{[^}]+\}/', 'sample', $path);
            foreach (array_keys($operations) as $method) {
                $router->getContext()->setMethod(strtoupper($method));
                try {
                    $router->match($url);
                } catch (ResourceNotFoundException $e) {
                    $this->fail(strtoupper($method)." $path is documented but no route matches it");
                }
            }
        }
        $this->addToAssertionCount(1);
    }

    /**
     * Get specification.
     */
    public function testSchemaValidatorArguments()
    {
        self::ensureKernelShutdown();
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
