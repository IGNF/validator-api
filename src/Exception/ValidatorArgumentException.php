<?php

namespace App\Exception;

use Exception;

class ValidatorArgumentException extends Exception
{
    public function __construct($message, $code = 400, Exception $previous = null)
    {
        $this->message = $message;
        $this->code = $code;
        $this->previous = $previous;
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
