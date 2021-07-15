<?php

namespace App\Storage ;

use App\Entity\Validation;

/**
 * Manage validation files
 */
class ValidationsStorage {

    /**
     * Root directory for validations.
     *
     * @var string
     */
    private $path;

    public function __construct($validationsDir)
    {
        $this->path = $validationsDir;
    }

    /**
     * @return string
     */
    public function getPath(){
        return $this->path;
    }

    /**
     * @param Validation $validation
     * @return string
     */
    public function getDirectory(Validation $validation){
        return $this->path.'/'.$validation->getUid();
    }


}

