<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Storage\ValidationsStorage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Manages the local working directory of a validation and its persisted
 * files in storage: staging the uploaded archive, unzipping it, zipping
 * normalized results, saving output to storage and removing local/persisted
 * files.
 */
class ValidationWorkspace
{
    public function __construct(
        private ValidationsStorage $storage,
        private LoggerInterface $logger,
    ) {}

    /**
     * Returns the path to the local (working directory) zip file.
     */
    public function getLocalZipPath(Validation $validation): string
    {
        return $this->storage->getDirectory($validation) . '/' . $validation->getDatasetName() . '.zip';
    }

    /**
     * Copies the uploaded zip file from storage to the local working directory.
     */
    public function prepareUpload(Validation $validation): void
    {
        $this->logger->info('Validation[{uid}] : get from storage...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);

        $validationDirectory = $this->storage->getDirectory($validation);
        $uploadFile = $this->storage->getUploadDirectory($validation) . $validation->getDatasetName() . '.zip';

        if (!is_dir($validationDirectory)) {
            mkdir($validationDirectory, recursive: true);
        }

        file_put_contents(
            $this->getLocalZipPath($validation),
            $this->storage->getStorage()->read($uploadFile)
        );
    }

    /**
     * Extracts the local zip archive.
     */
    public function unzip(Validation $validation): void
    {
        $this->logger->info('Validation[{uid}] : extract source archive...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);
        $zip = new ZipArchive();

        if (true === $zip->open($this->getLocalZipPath($validation))) {
            $zip->extractTo($validationDirectory . '/' . $validation->getDatasetName());
            $zip->close();
        } else {
            throw new Exception('Zip decompression failed');
        }
    }

    /**
     * Zips the generated normalized data, if present.
     */
    public function zipNormalizedData(Validation $validation): void
    {
        $this->logger->info('Validation[{uid}] : compress normalized data...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $fs = new Filesystem();

        $validationDirectory = $this->storage->getDirectory($validation);
        $normDataParentDir = $validationDirectory . '/validation/';
        $datasetName = $validation->getDatasetName();

        // checking if normalized data is present
        if (!$fs->exists($normDataParentDir . $datasetName)) {
            return;
        }

        $process = new Process(['zip', '-r', "$datasetName.zip", $datasetName], $normDataParentDir);
        $process->setTimeout(600);
        $process->setIdleTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Saves the normalized data and validator debug log from the local
     * working directory to persistent storage.
     */
    public function saveToStorage(Validation $validation): void
    {
        // Saves normalized data to storage
        $this->logger->info('Validation[{uid}] : saving normalized data...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);
        $normDataPath = $validationDirectory . '/validation/' . $validation->getDatasetName() . '.zip';
        $outputDirectory = $this->storage->getOutputDirectory($validation);
        if (!$this->storage->getStorage()->directoryExists($outputDirectory)) {
            $this->storage->getStorage()->createDirectory($outputDirectory);
        }
        $outputPath = $outputDirectory . $validation->getDatasetName() . '.zip';
        if ($this->storage->getStorage()->fileExists($outputPath)) {
            $this->storage->getStorage()->delete($outputPath);
        }
        $stream = fopen($normDataPath, 'r+');
        $this->storage->getStorage()->writeStream($outputPath, $stream);
        fclose($stream);

        // Saves validator logs to storage
        $this->logger->info('Validation[{uid}] : saving logs...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $logPath = $validationDirectory . '/validator-debug.log';
        $outputPath = $outputDirectory . '/validator-debug.log';

        $stream = fopen($logPath, 'r+');
        $this->storage->getStorage()->writeStream($outputPath, $stream);
        fclose($stream);
    }

    /**
     * Removes the local working directory, if present.
     */
    public function removeLocalDirectory(Validation $validation): void
    {
        $validationDirectory = $this->storage->getDirectory($validation);

        $fs = new Filesystem();
        if ($fs->exists($validationDirectory)) {
            $this->logger->debug('Validation[{uid}] : remove validation directory ...', [
                'uid' => $validation->getUid(),
                'validationDirectory' => $validationDirectory,
            ]);
            $fs->remove($validationDirectory);
        }
    }

    /**
     * Removes the upload and output directories from persistent storage.
     */
    public function removePersistedFiles(Validation $validation): void
    {
        $this->logger->info('Validation[{uid}] : remove upload files', [
            'uid' => $validation->getUid(),
        ]);
        $uploadDirectory = $this->storage->getUploadDirectory($validation);
        if ($this->storage->getStorage()->directoryExists($uploadDirectory)) {
            $this->storage->getStorage()->deleteDirectory($uploadDirectory);
        }
        $this->logger->info('Validation[{uid}] : remove output files', [
            'uid' => $validation->getUid(),
        ]);
        $outputDirectory = $this->storage->getOutputDirectory($validation);
        if ($this->storage->getStorage()->directoryExists($outputDirectory)) {
            $this->storage->getStorage()->deleteDirectory($outputDirectory);
        }
    }
}
