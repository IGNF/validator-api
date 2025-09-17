<?php

namespace App\Service;

use App\Exception\ApiException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Response;

class ValidatorArgumentsService
{
    private $projectDir;

    public function __construct($projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Validates the arguments posted by the user
     *
     * @param string $args
     * @return array
     * @throws ApiException
     */
    public function validate(string $args)
    {
        $args = json_decode($args);

        $validator = new Validator();
        $validator->validate($args, (object) ['$ref' => 'file://' . $this->projectDir . '/docs/specs/schema/validator-arguments.json'], Constraint::CHECK_MODE_APPLY_DEFAULTS);

        if ($validator->isValid()) {
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

            throw new ApiException("Invalid arguments, check details", Response::HTTP_BAD_REQUEST, $details);
        }
    }

}
