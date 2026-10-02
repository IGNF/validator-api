<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Exception\ValidationProcessException;
use App\Exception\ZipArchiveValidationException;
use App\Storage\ValidationsStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
        private ZipArchiveExtractor $zipArchiveExtractor,
    ) {
    }

    /**
     * Returns the path to the local (working directory) zip file.
     */
    public function getLocalZipPath(Validation $validation): string
    {
        return $this->storage->getDirectory($validation).'/'.$validation->getDatasetName().'.zip';
    }

    /**
     * Copies the uploaded zip file from storage to the local working directory.
     */
    public function prepareUpload(Validation $validation): void
    {
        // defense in depth : datasets uploaded before the name check was added
        if (!Validation::isValidDatasetName($validation->getDatasetName())) {
            throw new ValidationProcessException(sprintf("Invalid dataset name '%s'", $validation->getDatasetName()));
        }

        $this->logger->info('Validation[{uid}] : get from storage...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);

        $validationDirectory = $this->storage->getDirectory($validation);
        $uploadFile = $this->storage->getUploadDirectory($validation).$validation->getDatasetName().'.zip';

        if (!is_dir($validationDirectory)) {
            mkdir($validationDirectory, recursive: true);
        }

        file_put_contents(
            $this->getLocalZipPath($validation),
            $this->storage->getStorage()->read($uploadFile)
        );
    }

    /**
     * Extracts the local zip archive, checking the content of the files.
     *
     * @throws ZipArchiveValidationException if a file is not allowed
     */
    public function unzip(Validation $validation): void
    {
        $this->logger->info('Validation[{uid}] : extract source archive...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);

        $this->zipArchiveExtractor->extract(
            $this->getLocalZipPath($validation),
            $validationDirectory.'/'.$validation->getDatasetName()
        );
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
        $normDataParentDir = $validationDirectory.'/validation/';
        $datasetName = $validation->getDatasetName();

        // checking if normalized data is present
        if (!$fs->exists($normDataParentDir.$datasetName)) {
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
     * Saves the normalized data (if any, i.e. "normalize" argument enabled)
     * from the local working directory to persistent storage.
     */
    public function saveNormalizedData(Validation $validation): void
    {
        $normDataPath = $this->storage->getDirectory($validation).'/validation/'.$validation->getDatasetName().'.zip';
        if (!file_exists($normDataPath)) {
            $this->logger->info('Validation[{uid}] : no normalized data to save', [
                'uid' => $validation->getUid(),
            ]);

            return;
        }

        $this->logger->info('Validation[{uid}] : saving normalized data...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $this->writeToOutputDirectory($validation, $normDataPath, $validation->getDatasetName().'.zip');
    }

    /**
     * Saves the validator debug log (if any) from the local working directory to persistent storage,
     * so that it is available for failed validations too.
     */
    public function saveLog(Validation $validation): void
    {
        $logPath = $this->storage->getDirectory($validation).'/validator-debug.log';
        if (!file_exists($logPath)) {
            return;
        }

        $this->logger->info('Validation[{uid}] : saving logs...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $this->writeToOutputDirectory($validation, $logPath, 'validator-debug.log');
    }

    /**
     * Copies a local file to the output directory of the validation in persistent storage.
     */
    private function writeToOutputDirectory(Validation $validation, string $localPath, string $filename): void
    {
        $outputDirectory = $this->storage->getOutputDirectory($validation);
        if (!$this->storage->getStorage()->directoryExists($outputDirectory)) {
            $this->storage->getStorage()->createDirectory($outputDirectory);
        }
        $outputPath = $outputDirectory.$filename;
        if ($this->storage->getStorage()->fileExists($outputPath)) {
            $this->storage->getStorage()->delete($outputPath);
        }

        $stream = fopen($localPath, 'r');
        try {
            $this->storage->getStorage()->writeStream($outputPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
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
