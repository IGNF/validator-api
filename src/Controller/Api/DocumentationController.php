<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DocumentationController extends AbstractController
{
    /**
     * @var string
     */
    private $specsDir;

    public function __construct($projectDir)
    {
        $this->specsDir = $projectDir . '/docs/specs';
    }

    /**
     * Root path for the API displaying swagger.
     *
     * @Route("/validator/", name="validator_api_root")
     */
    public function index()
    {
        return $this->render('swagger.html.twig');
    }

    /**
     * Get OpenAPI specifications.
     *
     * @Route("/validator/validator-api.yml", name="validator_api_swagger")
     */
    public function swagger()
    {
        $swaggerPath = $this->specsDir . '/validator-api.yml';
        return new BinaryFileResponse($swaggerPath);
    }


    /**
     * Get a schema from docs/specs/schema.
     *
     * @Route("/validator/schema/{schemaName}.json", name="validator_api_schema", requirements={"schemaName"="[\w\-]+"})
     */
    public function schema($schemaName)
    {
        $schemaPath = $this->specsDir . '/schema/' . $schemaName . '.json';
        return new BinaryFileResponse($schemaPath);
    }
}
