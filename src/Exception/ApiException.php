<?php

namespace App\Exception;

use Exception;

class ApiException extends Exception
{
    protected $details;
    protected $previous;

    public function __construct($message, $code, $details = [], Exception $previous = null)
    {
        $this->message = $message;
        $this->details = $details;
        $this->code = $code;
        $this->previous = $previous;
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function getDetails()
    {
        return $this->details;
    }
}
