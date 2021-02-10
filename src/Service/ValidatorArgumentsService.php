<?php

namespace App\Service;

use App\Exception\ValidatorArgumentException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

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
     * @param array[mixed] $args
     * @return array[mixed]
     * @throws ValidatorArgumentException
     */
    public function validate($args)
    {
        $args = json_decode($args);

        $validator = new Validator();
        $validator->validate($args, (object) ['$ref' => 'file://' . $this->projectDir . '/docs/resources/validator-arguments.json'], Constraint::CHECK_MODE_APPLY_DEFAULTS);

        if ($validator->isValid()) {
            return get_object_vars($args);
        } else {
            $errors = [];

            foreach ($validator->getErrors() as $error) {
                array_push($errors, sprintf("[%s] %s", $error['property'], $error['message']));
            }

            throw new ValidatorArgumentException(sprintf("Arguments are invalid: %d error(s) found, check details", count($errors)), $errors);
        }
    }

}
