<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Exception\ZipArchiveValidationException;
use App\Repository\ValidationRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ValidationManager
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ValidationWorkspace
     */
    private $workspace;

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
     * @var ValidationRepository
     */
    private $validationRepository;

    /**
     * Current validation (in order to handle SIGTERM).
     *
     * @var Validation
     */
    private $currentValidation;

    public function __construct(
        EntityManagerInterface $em,
        ValidationWorkspace $workspace,
        ValidatorCLI $validatorCli,
        ZipArchiveValidator $zipArchiveValidator,
        LoggerInterface $logger,
        ValidationRepository $validationRepository,
    ) {
        $this->em = $em;
        $this->workspace = $workspace;
        $this->validatorCli = $validatorCli;
        $this->zipArchiveValidator = $zipArchiveValidator;
        $this->logger = $logger;
        $this->validationRepository = $validationRepository;
    }

    /**
     * Archive a given validation removing all local files.
     *
     * @return void
     */
    public function archive(Validation $validation)
    {
        $this->logger->info('Validation[{uid}] : archive removing all files...', [
            'uid' => $validation->getUid(),
        ]);
        $this->workspace->removeLocalDirectory($validation);
        $this->workspace->removePersistedFiles($validation);
        $this->logger->info('Validation[{uid}] : drop validation schema', [
            'uid' => $validation->getUid(),
        ]);
        $this->validationRepository->dropSchema($validation);
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
            $this->logger->debug('processOne : no validation pending, quitting');

            return;
        }
        $this->currentValidation = $validation;
        $this->doProcess($validation);
        $this->currentValidation = null;
    }

    /**
     * Stop currently running validation (invoked when SIGTERM is received).
     *
     * @return void
     */
    public function cancelProcessing()
    {
        if (is_null($this->currentValidation)) {
            $this->logger->debug('SIGTERM received, no validation in progress');

            return;
        }
        $this->logger->warning('Validation[{uid}]: SIGTERM received, changing state to pending', [
            'uid' => $this->currentValidation->getUid(),
        ]);
        $this->currentValidation->setStatus(Validation::STATUS_PENDING);
        $this->em->persist($this->currentValidation);
        $this->em->flush();
    }

    /**
     * Process pending validation.
     *
     * @return void
     */
    private function doProcess(Validation $validation)
    {
        $this->logger->info('Validation[{uid}]: process pending validation...', ['uid' => $validation->getUid()]);

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
            $this->workspace->prepareUpload($validation);

            /*
             * pre-validating the names of the files in the zip archive
             */
            $this->validateZip($validation);

            /*
             * unzip dataset
             */
            $this->workspace->unzip($validation);

            /*
             * run validator-cli.jar command
             */
            $this->validatorCli->process($validation);

            /*
             * zip normalized results
             */
            $this->workspace->zipNormalizedData($validation);

            /*
             * Save validation data to storage
             */
            $this->workspace->saveToStorage($validation);

            /*
             * cleanup data
             */
            $this->cleanUp($validation);

            if ($validation->getStatus() != Validation::STATUS_ARCHIVED) {
                $validation->setStatus(Validation::STATUS_FINISHED);
            }
            $this->logger->info('Validation[{uid}]: validation carried out successfully', ['uid' => $validation->getUid()]);
        } catch (ZipArchiveValidationException $ex) {
            $validation->setStatus(Validation::STATUS_ERROR);
            $validation->setMessage($ex->getMessage());
            $validation->setResults($ex->getErrors());
            $this->logger->error('Validation[{uid}]: {message}: {errors}', ['uid' => $validation->getUid(), 'message' => $ex->getMessage(), 'errors' => $ex->getErrors()]);
        } catch (\Throwable $th) {
            $validation->setStatus(Validation::STATUS_ERROR);
            $validation->setMessage($th->getMessage());
            $this->logger->error('Validation[{uid}]: {message}', ['uid' => $validation->getUid(), 'message' => $th->getMessage()]);
        }

        $validation->setDateFinish(new DateTime('now'));
        $this->em->persist($validation);
        $this->em->flush();
    }

    /**
     * Pre-validates the names of files in the zip.
     *
     * @param Validation $validation
     *
     * @return void
     *
     * @throws ZipArchiveValidationException
     */
    private function validateZip($validation)
    {
        $this->logger->info('Validation[{uid}] : validate zip archive...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $errors = $this->zipArchiveValidator->validate($this->workspace->getLocalZipPath($validation));
        if (count($errors) > 0) {
            throw new ZipArchiveValidationException($errors);
        }
    }

    /**
     * Cleans up temporary files.
     *
     * @return void
     */
    private function cleanUp(Validation $validation)
    {
        $this->logger->info('Validation[{uid}] : cleanup...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $this->workspace->removeLocalDirectory($validation);

        if ($validation->getDeleteData()) {
            $this->archive($validation);
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
