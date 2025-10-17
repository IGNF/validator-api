<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Storage\ValidationsStorage;
use Doctrine\ORM\EntityManagerInterface;
use FFI\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Exception\ZipArchiveValidationException;

class ValidationManager
{

    private EntityManagerInterface $em;
    private ValidationsStorage $storage;
    private ValidatorCLI $validatorCli;
    private LoggerInterface $logger;
    private ZipArchiveValidator $zipArchiveValidator;
    private ?Validation $currentValidation = null;
    private HttpClientInterface $client;

    public function __construct(
        EntityManagerInterface $em,
        ValidationsStorage $storage,
        ValidatorCLI $validatorCli,
        ZipArchiveValidator $zipArchiveValidator,
        LoggerInterface $logger,
        HttpClientInterface $client,
    ) {
        $this->em = $em;
        $this->storage = $storage;
        $this->validatorCli = $validatorCli;
        $this->zipArchiveValidator = $zipArchiveValidator;
        $this->logger = $logger;
        $this->client = $client;
    }

    /**
     * Archive a given validation removing all local files.
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

        // Delete from storage
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
        $this->storage->init($validation);
        $this->doProcess($validation);

        $validation->setProcessing(false);
        $this->em->persist($validation);
        $this->em->flush();

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
        $this->currentValidation->setStatus(Validation::STATUS_ABORTED);
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

        try {
            switch($validation->getStatus()) {

                case Validation::STATUS_WAITING_ARGS :
                    $this->logger->info( "Validation[{uid}]: Validating arguments...", ['uid' => $validation->getUid()]);
                    $this->validateArgs($validation);
                    break;

                case Validation::STATUS_UPLOADABLE :
                    $this->logger->info( "Validation[{uid}]: Uploading...", ['uid' => $validation->getUid()]);
                    $this->upload($validation);
                    break;

                case Validation::STATUS_PATCHABLE :
                    $this->logger->info( "Validation[{uid}]: Patching arguments...", ['uid' => $validation->getUid()]);
                    $this->patch($validation);
                    break;

                case Validation::STATUS_WAITING_VALIDATION :
                    $this->logger->info( "Validation[{uid}]: Waiting on validation...", ['uid' => $validation->getUid()]);
                    $this->pingAPI($validation);
                    break;

                case Validation::STATUS_VALIDATED :
                    $this->logger->info( "Validation[{uid}]: Cleaning up...", ['uid' => $validation->getUid()]);
                    $this->cleanUp($validation);
                    break;
            }

        } catch (\Throwable $th) {
            // $validation->setStatus(Validation::STATUS_ERROR);
            $validation->setMessage($th->getMessage());
            $this->logger->error("Validation[{uid}]: {message}", ['uid' => $validation->getUid(), 'message' => $th->getMessage()]);
        }
    }

    /**
     * Asserting Args conforming to validation model
     * @param Validation $validation
     * @return void
     */
    private function validateArgs(Validation $validation): void
    {
        if(($validation->getModel() == "canalisations") && ($validation.getKeepData())) {
            throw new ParseException("Le modèle Canalisation n'a pas la possibilité de conserver les données.", );
        }
        $validation->setStatus(Validation::STATUS_UPLOADABLE);
    }

    private function upload(Validation $validation): void
    {

        $formFields = [
            'dataset' => DataPart::fromPath($this->storage->getSource())
        ];
        $formData = new FormDataPart($formFields);

        $response = $this->client->request('POST', $_ENV['API_URL'],
        [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable()
        ]);
        if ($response->getStatusCode() != Response::HTTP_CREATED) {
            throw new ParseException(sprintf("Erreur dans l'envoi de fichier à l'API : %s", $response->toArray()['error']));
        }
        // $statusCode = 201
        $validation->setApiId($response->toArray()['uid']);

        $validation->setStatus(Validation::STATUS_PATCHABLE);
    }

    private function patch(Validation $validation): void
    {
        // $body = [
        //         "model" => $validation->getModel(),
        //         "srs" => $validation->getSRS(),
        //         "max-errors" => 30,
        //         "normalize" => true,
        //         "plugins" => $validation->getPlugins(),
        //         "encoding" => "UTF-8",
        //         "dgpr-tolerance" => 10,
        //         "dgpr-simplify" => 2,
        //         "dgpr-safe-simplify" => true
        // ];
        // $response = $this->client->request('PATCH', $_ENV['API_URL'] . $validation->getApiId(),
        // [
        //     'body' => json_encode($body)
        // ]);
        // if ($response->getStatusCode() != Response::HTTP_OK) {
        //     throw new ParseException(sprintf("Erreur dans l'envoi des paramètres à l'API : %s", $response->toArray()['error']));
        // }

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

        // $statusCode = 200
        $validation->setStatus(Validation::STATUS_WAITING_VALIDATION);
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
        $this->logger->info('Validation[{uid}] : validate zip archive...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);        $zipPath = $this->storage->getSource();
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
        $validationDirectory = './public/' . $this->storage->getPath();
        $zipFilename = $this->storage->getSource();
        $zip = new \ZipArchive();

        if ($zip->open($zipFilename) === true) {
            $zip->extractTo($validationDirectory . '/' . $validation->getDatasetName());
            $zip->close();
        } else {
            throw new \Exception("Zip decompression failed");
        }
    }

    private function pingAPI(Validation $validation): void
    {
        // $response = $this->client->request('GET', $_ENV['API_URL'] . $validation->getApiId());

        // if ($response->getStatusCode() != Response::HTTP_OK) {
        //     throw new ParseException("Erreur dans l'obtention des informations de la validation.");
        // }

        $this->logger->info('Validation[{uid}] : Checking if validation is over...', [
            'uid' => $validation->getUid(),
        ]);
        if ($this->storage->getReportPathExists())
        {
            $this->logger->info('Validation[{uid}] : Validation is over...', [
                'uid' => $validation->getUid(),
            ]);
            $validation->setStatus(Validation::STATUS_VALIDATED);
        }
        // $statusCode = 200
    }

    /**
     * Cleans up temporary files
     *
     * @return void
     */
    private function cleanUp(Validation $validation)
    {

        $validation->setStatus(Validation::STATUS_ARCHIVED);

        $this->logger->info('Validation[{uid}] : cleanup...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);

        $this->logger->info('Validation[{uid}]: Removing API data...', [
            'uid' => $validation->getUid(),
        ]);



        $this->storage->cleanLocal();

        // clean gpf
    }

    /**
     * @return ValidationRepository
     */
    protected function getValidationRepository()
    {
        return $this->em->getRepository(Validation::class);
    }

}
