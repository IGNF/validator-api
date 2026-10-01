<?php

namespace App\Service;

use App\Exception\ApiException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Response;

class ValidatorArgumentsService
{
    private $projectDir;

    /**
     * Hosts (and their subdomains) allowed for the model url, to prevent SSRF from validator-cli.jar.
     *
     * @var string[]
     */
    private array $modelAllowedHosts;

    public function __construct($projectDir, string $modelAllowedHosts)
    {
        $this->projectDir = $projectDir;
        $this->modelAllowedHosts = array_filter(array_map(
            fn ($host) => strtolower(trim($host)),
            explode(',', $modelAllowedHosts)
        ));
    }

    /**
     * Validates the arguments posted by the user.
     *
     * @param string $args arguments as a JSON string
     *
     * @return array
     *
     * @throws ApiException
     */
    public function validate($args)
    {
        $args = json_decode($args);

        $validator = new Validator();
        $validator->validate($args, (object) ['$ref' => 'file://'.$this->projectDir.'/docs/specs/schema/validator-arguments.json'], Constraint::CHECK_MODE_APPLY_DEFAULTS);

        if ($validator->isValid()) {
            $this->validateModelHost($args->model);

            return get_object_vars($args);
        } else {
            $details = [];

            foreach ($validator->getErrors() as $error) {
                $errorDetails = [];
                if ($error['property']) {
                    $errorDetails['name'] = $error['property'];
                }
                $errorDetails['message'] = $error['message'];

                array_push($details, $errorDetails);
            }

            throw new ApiException('Invalid arguments, check details', Response::HTTP_BAD_REQUEST, $details);
        }
    }

    /**
     * Ensures that the host of the model url (already checked by the schema pattern) is allowed.
     *
     * @throws ApiException
     */
    private function validateModelHost(string $model): void
    {
        $host = strtolower((string) parse_url($model, PHP_URL_HOST));
        foreach ($this->modelAllowedHosts as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return;
            }
        }

        throw new ApiException('Invalid arguments, check details', Response::HTTP_BAD_REQUEST, [[
            'name' => 'model',
            'message' => sprintf("Host '%s' is not allowed", $host),
        ]]);
    }
}
