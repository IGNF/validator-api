<?php

namespace App\Service;

use App\Exception\ValidatorArgumentException;

class ValidatorArgumentsService
{
    private $projectDir;

    private $args;

    public function __construct($projectDir)
    {
        $this->projectDir = $projectDir;
        $this->load();
    }

    /**
     * Loads the arguments configuration from pre-defined file
     *
     * @return void
     */
    public function load()
    {
        $this->args = \json_decode(\file_get_contents($this->projectDir . '/resources/validator-arguments.json'), true);
    }

    /**
     * Validates the arguments posted by the user
     *
     * @param array[mixed] $postedArgs
     * @return array[mixed]
     */
    public function validate($postedArgs)
    {
        $this->validateRequiredArgs($postedArgs);
        $defaultValues = $this->validateArgsDefault($postedArgs);

        // validating specific arguments
        // projection srid
        if (!$this->sridIsAccepted($postedArgs['srs'])) {
            throw new ValidatorArgumentException(sprintf("The provided srid [%s] is not accepted by validator", $postedArgs['srs']));
        }

        // model url
        if (!filter_var($postedArgs['model'], FILTER_VALIDATE_URL)) {
            throw new ValidatorArgumentException(sprintf("The provided model url [%s] is not valid", $postedArgs['model']));
        }

        $args = array_merge($postedArgs, $defaultValues);
        return $args;
    }

    /**
     * Checks if required arguments are provided, error if required argument missing
     *
     * @param array[mixed] $postedArgs
     * @return void
     */
    private function validateRequiredArgs($postedArgs)
    {
        $reqArgs = $this->getRequiredArgs();

        foreach ($reqArgs as $rArgName => $rArgVal) {
            if (!array_key_exists($rArgName, $postedArgs)) {
                throw new ValidatorArgumentException(\sprintf("Arguments [%s] are required", \implode(', ', array_keys($reqArgs))));
            }
        }
    }

    /**
     * Checks if arguments with a default value are overridden, error if override_allowed is false
     * Returns an array with default values
     *
     * @param array[mixed] $postedArgs
     * @return void
     */
    private function validateArgsDefault($postedArgs)
    {
        $args = array_filter($this->args, function ($arg) {
            return array_key_exists('default_value', $arg);
        });

        // checking if overriding the posted arguments is allowed
        foreach ($postedArgs as $argName => $arg) {
            if (\array_key_exists($argName, $args) && !$args[$argName]['override_allowed']) {
                throw new ValidatorArgumentException(sprintf("Overriding argument [%s] is not allowed", $argName));
            }
        }

        // assigning default values, if not overridden by user
        $defaultValues = [];
        foreach ($args as $argName => $arg) {
            if (!array_key_exists($argName, $postedArgs)) {
                $defaultValues[$argName] = $arg['default_value'];
            }
        }

        return $defaultValues;
    }

    /**
     * Returns only the required arguments
     *
     * @return array[mixed]
     */
    private function getRequiredArgs()
    {
        return array_filter($this->args, function ($arg) {
            return $arg['required'];
        });
    }

    /**
     * Returns true if the provided projection srid is accepted by validator
     *
     * @return bool
     */
    private function sridIsAccepted($postedSrid)
    {
        // reading from projections config file
        $projections = \json_decode(\file_get_contents($this->projectDir . '/resources/projection.json'), true);

        foreach ($projections as $value) {
            if ($value['code'] == $postedSrid) {
                return true;
            }
        }

        return false;
    }
}
