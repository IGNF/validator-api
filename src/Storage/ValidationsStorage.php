<?php

namespace App\Storage ;

use App\Entity\Validation;
use League\Flysystem\FilesystemOperator;

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

    /**
     * Flysystem storage
     *
     * @var FilesystemOperator
     */
    private $storageSystem;

    public function __construct($validationsDir,
        FilesystemOperator $dataStorage,
        FilesystemOperator $defaultStorage)
    {
        $this->path = $validationsDir;
        // Assign storage based on env
        if (getenv("STORAGE_TYPE") === "S3"){
            $this->storageSystem = $dataStorage;
        } else {
            $this->storageSystem = $defaultStorage;
        }
    }

    /**
     * @return string
     */
    public function getPath(){
        return $this->path;
    }

    /**
     * @return FilesystemOperator
     */
    public function getStorage(){
        return $this->storageSystem;
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

