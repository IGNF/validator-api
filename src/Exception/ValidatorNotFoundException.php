<?php

namespace App\Exception;

use RuntimeException;

class ValidatorNotFoundException extends RuntimeException {

    public function __construct($validatorPath)
    {
        parent::__construct(sprintf(
            "validator-cli.jar not found (VALIDATOR_PATH='%s' not found)",
            $validatorPath
        ));
    }

}
