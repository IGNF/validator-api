<?php

namespace App\Controller\Api;

use App\Exception\ApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class DocumentationController extends AbstractController
{
    /**
     * Paths flagged as disabled in the specification when DATA_DOWNLOAD_ENABLED is off.
     */
    private const DATA_DOWNLOAD_PATHS = [
        '/api/validations/{uid}/files/source',
        '/api/validations/{uid}/files/normalized',
    ];

    private const DISABLED_MESSAGE = 'Route désactivée temporairement pour des raisons de sécurité.';

    /**
     * @var string
     */
    private $specsDir;

    public function __construct(
        $projectDir,
        private bool $dataDownloadEnabled,
    ) {
        $this->specsDir = $projectDir.'/docs/specs';
    }

    /**
     * Root path for the API : redirects to the swagger displayed by the demo client.
     */
    #[Route('/api', name: 'validator_api_root', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirect($this->generateUrl('validator_api_demo').'#/api');
    }

    /**
     * Get OpenAPI specifications.
     */
    #[Route('/api/validator-api.yml', name: 'validator_api_swagger', methods: ['GET'])]
    public function swagger()
    {
        $swaggerPath = $this->specsDir.'/validator-api.yml';

        if ($this->dataDownloadEnabled) {
            return new BinaryFileResponse($swaggerPath);
        }

        // disabled routes stay documented, flagged as deprecated (greyed out by swagger-ui)
        // and with "x-disabled" (read by the demo client to hide the matching downloads)
        $specs = (new Parser())->parseFile($swaggerPath);
        foreach (self::DATA_DOWNLOAD_PATHS as $path) {
            foreach ($specs['paths'][$path] as $method => $operation) {
                $operation['deprecated'] = true;
                $operation['x-disabled'] = true;
                $operation['summary'] = '[Désactivé] '.($operation['summary'] ?? '');
                $operation['description'] = '**'.self::DISABLED_MESSAGE."**\n\n".($operation['description'] ?? '');
                $specs['paths'][$path][$method] = $operation;
            }
        }

        return new Response(
            (new Dumper(2))->dump($specs, 20, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * Get a schema from docs/specs/schema.
     */
    #[Route('/api/schema/{schemaName}.json', name: 'validator_api_schema', requirements: ['schemaName' => '[\w\-]+'], methods: ['GET'])]
    public function schema($schemaName)
    {
        $fs = new Filesystem();
        $schemaPath = $this->specsDir.'/schema/'.$schemaName.'.json';

        if ($fs->exists($schemaPath)) {
            return new BinaryFileResponse($schemaPath);
        }
        throw new ApiException("No schema found with name=$schemaName", Response::HTTP_NOT_FOUND);

    }
}
