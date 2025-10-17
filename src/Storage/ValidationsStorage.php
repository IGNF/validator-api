<?php

namespace App\Storage ;

use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Validation;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * Manage validation files
 */
class ValidationsStorage {

    /**
     * Root directory for validations.
     *
     * @var string
     */
    private string $path = './var/data/';

    private Validation $validation;

    /**
     * Flysystem storage
     *
     * @var Filesystem
     */
    private Filesystem $fs;

    public function __construct(
        private LoggerInterface $logger,
    )
    {
        $this->fs = new Filesystem();
    }

    public function init(Validation $validation)
    {
        $this->validation = $validation;
        $this->path = $this->path . $validation->getUid();

        if (! $this->fs->exists($this->path)){
            $this->logger->debug('Validation[{uid}] : mkdir {path}...', [
                    'path' => $this->path,
            ]);
            $this->fs->mkdir($this->path);
        }
    }

    /**
     * @return string
     */
    public function getPath(){
        return $this->path;
    }

    public function getSource()
    {
        return './public/' . $this->path . '/' . $this->validation->getDatasetName() . '.zip';
    }

    /**
     * Write file from Path to system
     */
    public function write($file, bool $cleanup = false)
    {
        $fromPath = $file->getRealPath();

        if (! $this->fs->exists($fromPath)){
            throw new Exception(
                sprintf('Validation[{uid}]: File {file} does not exists', ['uid' => $this->validation->getUid(), 'file' => $fromPath])
            );
        }
        $this->logger->debug('Validation[{uid}]: cp {from} {to}...', [
                'uid' => $this->validation->getUid(),
                'from' => $fromPath,
                'to' => $this->getSource()
        ]);

        $file->move($this->path, $this->validation->getDatasetName() . '.zip');

        if($cleanup) {
            $this->logger->debug('Validation[{uid}]: rm -rf {path}...', [
                'uid' => $this->validation->getUid(),
                'path' => $fromPath,
            ]);
            $this->fs->remove($fromPath);
        }
    }

    public function cleanLocal()
    {
        $this->logger->info('Validation[{uid}]: Removing local data...', [
            'uid' => $this->validation->getUid(),
        ]);
        $this->fs->remove($this->path);
        $this->fs->remove('./public/' . $this->path);

    }

    public function getReportPathExists()
    {
        return true;
    }


}

