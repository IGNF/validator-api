<?php

namespace App\Exception;

use Exception;

class ZipArchiveValidationException extends Exception
{

    protected $errors;

    public function __construct($errors = [])
    {
        $this->errors = $errors;
        $this->message = "Zip archive pre-validation failed";
        parent::__construct();
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
