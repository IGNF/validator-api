<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Exception\ValidationProcessException;
use App\Exception\ZipArchiveValidationException;
use App\Repository\ValidationRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

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
     * @var Validation|null
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
        $this->removeFiles($validation);
        $this->logger->info('Validation[{uid}] : archive removing all files : completed', [
            'uid' => $validation->getUid(),
            'status' => Validation::STATUS_ARCHIVED,
        ]);
        $validation->setStatus(Validation::STATUS_ARCHIVED);
        $this->em->persist($validation);
        $this->em->flush();
    }

    /**
     * Marks as failed a validation that is still "processing" while no worker handles it anymore
     * (worker killed, out of memory...). It is not restarted as the dataset may be the cause.
     */
    public function markInterrupted(Validation $validation): void
    {
        $this->logger->warning('Validation[{uid}] : processing interrupted, changing state to error', [
            'uid' => $validation->getUid(),
            'dateStart' => $validation->getDateStart(),
        ]);
        $this->workspace->removeLocalDirectory($validation);
        $validation->setStatus(Validation::STATUS_ERROR);
        $validation->setMessage('Validation failed (processing interrupted)');
        $validation->setDateFinish(new DateTime('now'));
        $this->em->persist($validation);
        $this->em->flush();
    }

    /**
     * Delete a given validation removing all its files and its database schema.
     *
     * Files are removed first so that a storage error doesn't leave orphan files.
     */
    public function delete(Validation $validation): void
    {
        $this->logger->info('Validation[{uid}] : removing all saved data...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        $this->removeFiles($validation);
        $this->em->remove($validation);
        $this->em->flush();
    }

    /**
     * Process next pending validation.
     *
     * @return void
     */
    public function processOne()
    {
        $validation = $this->validationRepository->popNextPending();
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
        /*
         * the console application exits after the signal handler (finally blocks are not executed) :
         * stop validator-cli.jar and remove the local directory so that the validation can be restarted cleanly
         */
        try {
            $this->validatorCli->stop();
            $this->workspace->removeLocalDirectory($this->currentValidation);
        } catch (\Throwable $th) {
            $this->logger->error('Validation[{uid}]: fail to stop processing', [
                'uid' => $this->currentValidation->getUid(),
                'exception' => $th,
            ]);
        }
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
             * pre-validating the zip archive (sizes, paths, names and extensions of the files)
             */
            $this->validateZip($validation);

            /*
             * unzip dataset (checking the content of the files)
             */
            $this->workspace->unzip($validation);

            /*
             * run validator-cli.jar command
             */
            $this->validatorCli->process($validation);

            /*
             * zip normalized results and save them to storage
             */
            $this->workspace->zipNormalizedData($validation);
            $this->workspace->saveNormalizedData($validation);

            $validation->setStatus(Validation::STATUS_FINISHED);
            $this->logger->info('Validation[{uid}]: validation carried out successfully', ['uid' => $validation->getUid()]);
        } catch (ZipArchiveValidationException $ex) {
            $validation->setStatus(Validation::STATUS_ERROR);
            $validation->setMessage($ex->getMessage());
            $validation->setResults($ex->getErrors());
            $this->logger->error('Validation[{uid}]: {message}: {errors}', ['uid' => $validation->getUid(), 'message' => $ex->getMessage(), 'errors' => $ex->getErrors()]);
        } catch (\Throwable $th) {
            $validation->setStatus(Validation::STATUS_ERROR);
            $validation->setMessage($this->getPublicMessage($th));
            $this->logger->error('Validation[{uid}]: {message}', ['uid' => $validation->getUid(), 'message' => $th->getMessage(), 'exception' => $th]);
        } finally {
            /*
             * save logs and cleanup data, whatever the result of the validation
             */
            $this->cleanUp($validation);
        }

        $validation->setDateFinish(new DateTime('now'));
        $this->em->persist($validation);
        $this->em->flush();
    }

    /**
     * Returns the message stored in the validation (publicly readable) for an error.
     *
     * Raw messages may contain internal information (command lines, paths, java output...),
     * they are only kept for errors designed to be returned to the user.
     */
    private function getPublicMessage(\Throwable $throwable): string
    {
        if ($throwable instanceof ValidationProcessException) {
            return $throwable->getMessage();
        }
        if ($throwable instanceof ProcessFailedException) {
            return sprintf('Validation failed (exit code %s)', $throwable->getProcess()->getExitCode());
        }
        if ($throwable instanceof ProcessTimedOutException) {
            return sprintf('Validation failed (not completed after %d seconds)', $throwable->getExceededTimeout());
        }

        return 'Validation failed (internal error)';
    }

    /**
     * Pre-validates the zip archive (zip bomb, unsafe entries, names and extensions of the files).
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
     * Saves the validator log, removes the local working directory and,
     * if requested (delete-data), the persisted files.
     *
     * Errors are logged but not raised so that the status of the validation is always saved.
     *
     * @return void
     */
    private function cleanUp(Validation $validation)
    {
        $this->logger->info('Validation[{uid}] : cleanup...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);
        try {
            $this->workspace->saveLog($validation);
        } catch (\Throwable $th) {
            $this->logger->error('Validation[{uid}] : fail to save logs', ['uid' => $validation->getUid(), 'exception' => $th]);
        }

        try {
            $this->workspace->removeLocalDirectory($validation);
            if ($validation->getDeleteData()) {
                $this->removeFiles($validation);
                // keep the error status so that the user knows that the validation failed
                if (Validation::STATUS_FINISHED === $validation->getStatus()) {
                    $validation->setStatus(Validation::STATUS_ARCHIVED);
                }
            }
        } catch (\Throwable $th) {
            $this->logger->error('Validation[{uid}] : fail to cleanup', ['uid' => $validation->getUid(), 'exception' => $th]);
        }
    }

    /**
     * Removes local files, persisted files and database schema of a validation.
     */
    private function removeFiles(Validation $validation): void
    {
        $this->workspace->removeLocalDirectory($validation);
        $this->workspace->removePersistedFiles($validation);
        $this->logger->info('Validation[{uid}] : drop validation schema', [
            'uid' => $validation->getUid(),
        ]);
        $this->validationRepository->dropSchema($validation);
    }
}
