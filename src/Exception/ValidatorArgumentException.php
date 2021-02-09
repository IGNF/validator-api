<?php

namespace App\Exception;

use Exception;

class ValidatorArgumentException extends Exception
{
    protected $errors;

    public function __construct($message, $errors, $code = 400, Exception $previous = null)
    {
        $this->message = $message;
        $this->errors = $errors;
        $this->code = $code;
        $this->previous = $previous;
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
