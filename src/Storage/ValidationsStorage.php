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

    /**
     * @param Validation $validation
     * @return string
     */
    public function getUploadDirectory(Validation $validation){
        return $validation->getUid() . "/upload/";
    }

    /**
     * @param Validation $validation
     * @return string
     */
    public function getOutputDirectory(Validation $validation){
        return $validation->getUid() . "/output/";
    }


}

