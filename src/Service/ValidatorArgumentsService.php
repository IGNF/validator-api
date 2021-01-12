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
     * Checks the config of the arguments in the config file
     * Called during deployment by the command app:args-config-check
     *
     * @return void
     * @throws Exception
     */
    public function checkConfig()
    {
        foreach ($this->args as $argName => $arg) {
            if (!\array_key_exists('type', $arg)) {
                throw new \Exception(sprintf("[type] is not specified for the argument [%s]", $argName));
            }

            if (!\array_key_exists('required', $arg)) {
                throw new \Exception(sprintf("[required] is not specified for the argument [%s]", $argName));
            }

            if (\array_key_exists('default_value', $arg) && !\array_key_exists('override_allowed', $arg)) {
                throw new \Exception(sprintf("[default_value] or [override_allowed] is not specified for the argument [%s], either both or none of these two should be specified", $argName));
            }

            if (\array_key_exists('override_allowed', $arg) && !\array_key_exists('default_value', $arg)) {
                throw new \Exception(sprintf("[default_value] or [override_allowed] is not specified for the argument [%s], either both or none of these two should be specified", $argName));
            }
        }
    }

    /**
     * Validates the arguments posted by the user
     *
     * @param array[mixed] $postedArgs
     * @return array[mixed]
     * @throws ValidatorArgumentException
     */
    public function validate($postedArgs)
    {
        $this->validateRequiredArgs($postedArgs);
        $this->validateUnknownArgs($postedArgs);
        $this->validateBooleanArgs($postedArgs);
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
     * @throws ValidatorArgumentException
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
     * Checks if an unknown argument is posted
     *
     * @param array[mixed] $postedArgs
     * @return void
     * @throws ValidatorArgumentException
     */
    private function validateUnknownArgs($postedArgs)
    {
        foreach ($postedArgs as $argName => $arg) {
            if (!array_key_exists($argName, $this->args)) {
                throw new ValidatorArgumentException(\sprintf("Argument [%s] is unknown", $argName));
            }
        }
    }

    /**
     * Checks if the boolean arguments values are correct
     *
     * @param array[mixed] $postedArgs
     * @return void
     * @throws ValidatorArgumentException
     */
    private function validateBooleanArgs($postedArgs)
    {
        $boolArgs = $this->getBooleanArgs();

        foreach ($postedArgs as $argName => $arg) {
            if (array_key_exists($argName, $boolArgs) && !\is_bool($arg)) {
                throw new ValidatorArgumentException(\sprintf("Argument [%s] is not a valid boolean value", $argName));
            }
        }
    }

    /**
     * Checks if arguments with a default value are overridden, error if override_allowed is false
     * Returns an array with default values
     *
     * @param array[mixed] $postedArgs
     * @return void
     * @throws ValidatorArgumentException
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
    public function getRequiredArgs()
    {
        return array_filter($this->args, function ($arg) {
            return $arg['required'];
        });
    }

    /**
     * Returns only the boolean type arguments
     *
     * @return array[mixed]
     */
    public function getBooleanArgs()
    {
        return array_filter($this->args, function ($arg) {
            return $arg['type'] == 'boolean';
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
