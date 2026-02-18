<?php

namespace App\Controller\Api;

use App\Exception\ApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DocumentationController extends AbstractController
{
    /**
     * @var string
     */
    private $specsDir;

    public function __construct($projectDir)
    {
        $this->specsDir = $projectDir.'/docs/specs';
    }

    /**
     * Root path for the API displaying swagger.
     *
     * @Route("/api", name="validator_api_root")
     */
    public function index()
    {
        return $this->render('swagger.html.twig');
    }

    /**
     * Get OpenAPI specifications.
     *
     * @Route("/api/validator-api.yml", name="validator_api_swagger")
     */
    public function swagger()
    {
        $swaggerPath = $this->specsDir.'/validator-api.yml';

        return new BinaryFileResponse($swaggerPath);
    }

    /**
     * Get a schema from docs/specs/schema.
     *
     * @Route("/api/schema/{schemaName}.json", name="validator_api_schema", requirements={"schemaName"="[\w\-]+"})
     */
    public function schema($schemaName)
    {
        $fs = new Filesystem();
        $schemaPath = $this->specsDir.'/schema/'.$schemaName.'.json';

        if ($fs->exists($schemaPath)) {
            return new BinaryFileResponse($schemaPath);
        } else {
            throw new ApiException("No schema found with name=$schemaName", Response::HTTP_NOT_FOUND);
        }
    }
}
