<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Exception\ZipArchiveValidationException;
use App\Storage\ValidationsStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use League\Flysystem\FilesystemOperator;

class ValidationManager
{

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ValidationsStorage
     */
    private $storage;

    /**
     * @var ValidatorCLI
     */
    private $validatorCli;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ZipArchiveValidator
     */
    private $zipArchiveValidator;

    /**
     * @var FilesystemOperator
     */
    private $dataStorage;

    /**
     * Current validation (in order to handle SIGTERM)
     * @var Validation
     */
    private $currentValidation = null;

    public function __construct(
        EntityManagerInterface $em,
        ValidationsStorage $storage,
        FilesystemOperator $dataStorage,
        ValidatorCLI $validatorCli,
        ZipArchiveValidator $zipArchiveValidator,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->storage = $storage;
        $this->dataStorage = $dataStorage;
        $this->validatorCli = $validatorCli;
        $this->zipArchiveValidator = $zipArchiveValidator;
        $this->logger = $logger;
    }

    /**
     * Archive a given validation removing all files.
     *
     * @param Validation $validation
     * @return void
     */
    public function archive(Validation $validation)
    {
        $this->logger->info('Validation[{uid}] : archive removing all files...', [
            'uid' => $validation->getUid(),
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);
        $fs = new Filesystem();
        if ($fs->exists($validationDirectory)) {
            $this->logger->debug('Validation[{uid}] : remove validation directory ...', [
                'uid' => $validation->getUid(),
                'validationDirectory' => $validationDirectory,
            ]);
            $fs->remove($validationDirectory);
        }
        $this->logger->info('Validation[{uid}] : archive removing all files : completed', [
            'uid' => $validation->getUid(),
            'status' => Validation::STATUS_ARCHIVED,
        ]);
        $validation->setStatus(Validation::STATUS_ARCHIVED);
        $this->em->persist($validation);
        $this->em->flush();
    }

    /**
     * Process next pending validation.
     *
     * @return void
     */
    public function processOne()
    {
        $validation = $this->getValidationRepository()->popNextPending();
        if (is_null($validation)) {
            $this->logger->debug("processOne : no validation pending, quitting");
            return;
        }
        $this->currentValidation = $validation;
        $this->doProcess($validation);
        $this->currentValidation = null;
    }

    /**
     * Stop currently running validation (invoked when SIGTERM is received)
     *
     * @return void
     */
    public function cancelProcessing(){
        if ( is_null($this->currentValidation) ){
            $this->logger->debug("SIGTERM received, no validation in progress");
            return ;
        }
        $this->logger->warning("Validation[{uid}]: SIGTERM received, changing state to pending",[
            "uid" => $this->currentValidation->getUid()
        ]);
        $this->currentValidation->setStatus(Validation::STATUS_PENDING);
        $this->em->persist($this->currentValidation);
        $this->em->flush();
    }

    /**
     * Process pending validation
     *
     * @param Validation $validation
     * @return void
     */
    private function doProcess(Validation $validation)
    {
        $this->logger->info("Validation[{uid}]: process pending validation...", ['uid' => $validation->getUid()]);

        /*
         * force usage of popNextPending to avoid concurrency problems.
         */
        if (Validation::STATUS_PROCESSING !== $validation->getStatus()) {
            $message = sprintf(
                'doProcess must be invoked on validation with status %s (current status is %s)',
                Validation::STATUS_PROCESSING,
                $validation->getStatus()
            );
            $this->logger->error($message, ['uid' => $validation->getUid()]);
            throw new RuntimeException($message);
        }

        try {
            /*
             * get files from storage
             */
            $this->getZip($validation);

            /*
             * pre-validating the names of the files in the zip archive
             */
            $this->validateZip($validation);

            /*
             * unzip dataset
             */
            $this->unzip($validation);

            /*
             * run validator-cli.jar command
             */
            $this->validatorCli->process($validation);

            /*
             * zip normalized results
             */
            $this->zipNormData($validation);

            /*
             * Save validation data to storage
             */
            $this->saveToStorage($validation);

            /*
             * cleanup data
             */
            $this->cleanUp($validation);

            $validation->setStatus(Validation::STATUS_FINISHED);
            $this->logger->info("Validation[{uid}]: validation carried out successfully", ['uid' => $validation->getUid()]);
        } catch (ZipArchiveValidationException $ex) {
            $validation->setStatus(Validation::STATUS_ERROR);
            $validation->setMessage($ex->getMessage());
            $validation->setResults($ex->getErrors());
            $this->logger->error("Validation[{uid}]: {message}: {errors}", ['uid' => $validation->getUid(), 'message' => $ex->getMessage(), 'errors' => $ex->getErrors()]);
        } catch (\Throwable $th) {
            $validation->setStatus(Validation::STATUS_ERROR);
            $validation->setMessage($th->getMessage());
            $this->logger->error("Validation[{uid}]: {message}", ['uid' => $validation->getUid(), 'message' => $th->getMessage()]);
        }

        $validation->setDateFinish(new \DateTime('now'));
        $this->em->persist($validation);
        $this->em->flush();
    }

    /**
     * Get Zip file from storage to validate
     * @param Validation $validation
     * @return void
     */
    private function getZip(Validation $validation)
    {
        $this->logger->info('Validation[{uid}] : Get from storage...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);

        $validationDirectory = $this->storage->getDirectory($validation);

        if (!is_dir($validationDirectory)) {
            mkdir($validationDirectory);
        }
        
        $zipPath = $validationDirectory . '/' . $validation->getDatasetName() . '.zip';

        file_put_contents(
            $zipPath,
            $this->dataStorage->read('upload/' . $validation->getDatasetName() . '.zip')
        );
    }

    /**
     * Pre-validates the names of files in the zip
     *
     * @param Validation $validation
     * @return void
     * @throws ZipArchiveValidationException
     */
    private function validateZip($validation)
    {
        $validationDirectory = $this->storage->getDirectory($validation);
        $zipPath = $validationDirectory . '/' . $validation->getDatasetName() . '.zip';
        $errors = $this->zipArchiveValidator->validate($zipPath);
        if (count($errors) > 0) {
            throw new ZipArchiveValidationException($errors);
        }
    }

    /**
     * Unzips the compressed dataset
     *
     * @param Validation $validation
     * @return void
     */
    private function unzip(Validation $validation)
    {
        $this->logger->info('Validation[{uid}] : extract source archive...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);
        $zipFilename = $validationDirectory . '/' . $validation->getDatasetName() . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipFilename) === true) {
            $zip->extractTo($validationDirectory . '/' . $validation->getDatasetName());
            $zip->close();
        } else {
            throw new \Exception("Zip decompression failed");
        }
    }

    /**
     * Zips the generated normalized data
     *
     * @param Validation $validation
     * @return void
     */
    private function zipNormData(Validation $validation)
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

        $process = new Process(["zip","-r","$datasetName.zip",$datasetName],$normDataParentDir);
        $process->setTimeout(600);
        $process->setIdleTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Saves normalized zip to storage
     */
    private function saveToStorage(Validation $validation)
    {
        $this->logger->info('Validation[{uid}] : saving...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);
        $normDataPath = $validationDirectory . '/validation/' . $validation->getDatasetName() . '.zip';

        if (! $this->dataStorage->directoryExists($validationDirectory . 'validation')){
            $this->dataStorage->createDirectory($validationDirectory . 'validation');
        }
        if ($this->dataStorage->fileExists($normDataPath)){
            $this->dataStorage->delete($normDataPath);
        }
        $stream = fopen($normDataPath, 'r+');
        $this->dataStorage->writeStream($normDataPath, $stream);
        fclose($stream);
    }

    /**
     * Cleans up temporary files
     *
     * @return void
     */
    private function cleanUp(Validation $validation)
    {
        $this->logger->info('Validation[{uid}] : cleanup...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);

        $fs = new FileSystem();
        $sourceDataDir = $validationDirectory . '/' . $validation->getDatasetName();
        if ($fs->exists($sourceDataDir)) {
            $this->logger->debug('Validation[{uid}] : rm -rf {uid}/{datasetName}/...', [
                'uid' => $validation->getUid(),
                'datasetName' => $validation->getDatasetName(),
            ]);
            $fs->remove($sourceDataDir);
        }

        // clean uncompressed normalized data
        $normDataDir = $validationDirectory . '/validation/' . $validation->getDatasetName();
        if ($fs->exists($normDataDir)) {
            $this->logger->debug('Validation[{uid}] : rm -rf {uid}/validation/{datasetName}...', [
                'uid' => $validation->getUid(),
                'datasetName' => $validation->getDatasetName(),
            ]);
            $fs->remove($normDataDir);
        }

        // clean validation temporary database
        $tempDatabase = $validationDirectory . '/validation/document_database.db';
        if ($fs->exists($tempDatabase)) {
            $this->logger->debug('Validation[{uid}] : rm -f {uid}/validation/document_database.db...', [
                'uid' => $validation->getUid(),
                'datasetName' => $validation->getDatasetName(),
            ]);
            $fs->remove($tempDatabase);
        }

        // clean validation temporary zip
        $tempZip = $validationDirectory . '/validation/' . $validation->getDatasetName() . '.zip';
        if ($fs->exists($tempZip)) {
            $this->logger->debug('Validation[{uid}] : rm -f {uid}/validation/{datasetName}.zip...', [
                'uid' => $validation->getUid(),
                'datasetName' => $validation->getDatasetName(),
            ]);
            $fs->remove($tempZip);
        }

        // clean validation directory
        if ($fs->exists($validationDirectory)) {
            $this->logger->debug('Validation[{uid}] : rm -rf {uid}', [
                'uid' => $validation->getUid(),
            ]);
            $fs->remove($validationDirectory);
        }
    }

    /**
     * @return ValidationRepository
     */
    protected function getValidationRepository()
    {
        return $this->em->getRepository(Validation::class);
    }

}
