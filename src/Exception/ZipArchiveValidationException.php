<?php

namespace App\Exception;

use Exception;

/**
 * Exception class for errors raised by ZipArchiveValidator.
 */
class ZipArchiveValidationException extends \Exception
{
    /**
     * Array of errors in the same format as validator-cli.jar.
     *
     * @var array
     */
    protected $errors;

    public function __construct($errors = [])
    {
        $this->errors = $errors;
        $this->message = 'Zip archive pre-validation failed';
        parent::__construct();
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
