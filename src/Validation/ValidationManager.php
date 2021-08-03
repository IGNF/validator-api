<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Storage\ValidationsStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
     * @var string
     */
    private $validatorPath;

    /**
     * GMLAS_CONFIG environment variable for validator-cli.jar to avoid lower case renaming for GML validation.
     *
     * @var string
     */
    private $gmlasConfigPath;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        EntityManagerInterface $em,
        ValidationsStorage $storage,
        $validatorPath,
        $gmlasConfigPath,
        LoggerInterface $logger
    )
    {
        $this->em = $em;
        $this->storage = $storage;
        $this->validatorPath = $validatorPath;
        $this->gmlasConfigPath = $gmlasConfigPath;
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
            'uid' => $validation->getUid()
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);
        $fs = new Filesystem();
        if ($fs->exists($validationDirectory)) {
            $this->logger->debug('Validation[{uid}] : remove validation directory ...', [
                'uid' => $validation->getUid(),
                'validationDirectory' => $validationDirectory
            ]);
            $fs->remove($validationDirectory);
        }
        $this->logger->info('Validation[{uid}] : archive removing all files : completed', [
            'uid' => $validation->getUid(),
            'status' => Validation::STATUS_ARCHIVED
        ]);
        $validation->setStatus(Validation::STATUS_ARCHIVED);
        $this->em->persist($validation);
        $this->em->flush();
    }

    /**
     * Process pending validation
     *
     * @param Validation $validation
     * @return void
     */
    public function process(Validation $validation)
    {
        $this->logger->info("Validation[{uid}]: process pending validation...", ['uid' => $validation->getUid()]);
        $validation->setStatus(Validation::STATUS_PROCESSING);
        $validation->setDateStart(new \DateTime('now'));
        $this->em->persist($validation);
        $this->em->flush();

        $validationDirectory = $this->storage->getDirectory($validation);
        /*
         * TODO : $this->validatorCli->process($validation);
         */
        try {
            /* unzip dataset */
            $this->unzip($validation);

            /* prepare validator-cli.jar command */
            $args = $this->reconstructArgs($validation);
            $sourceDataDir = $validationDirectory . '/' . $validation->getDatasetName();

            $env = $_ENV;
            $env['GMLAS_CONFIG'] = $this->gmlasConfigPath;

            $cmd = ['java', '-jar', $this->validatorPath, 'document_validator', '--input', $sourceDataDir];
            $cmd = \array_merge($cmd, $args);

            // executing validation program
            $this->logger->info("Validation[{uid}]: executing Java validation program", ['uid' => $validation->getUid()]);
            $process = new Process(
                $cmd,
                $validationDirectory, // note that validator-debug.log is located in current directory,
                $env
            );
            $process->setTimeout(600);
            $process->setIdleTimeout(600);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            /*
             * read validation report
             */
            $reportPath = $validationDirectory.'/validation/validation.jsonl';
            $results = \file_get_contents($reportPath);

            // jsonl to json_array
            $results = \str_replace("}\n{", "},\n{", $results);
            $results = "[" . $results . "]";
            $results = \json_decode($results, true);

            $validation->setResults($results);

            /*
             * zip normalized results
             */
            $this->zipNormData($validation);
            /*
             * cleanup input data
             */
            $this->cleanUp($validation);

            $validation->setStatus(Validation::STATUS_FINISHED);
            $this->logger->info("Validation[{uid}]: validation carried out successfully", ['uid' => $validation->getUid()]);
        } catch (\Throwable $th) {
            $validation->setStatus(Validation::STATUS_ERROR);
            $validation->setMessage($th->getMessage());
            $this->logger->error("Validation[{uid}]: {message}", ['uid' => $validation->getUid(), 'message' => $th->getMessage()]);
        }

        $validation->setDateFinish(new \DateTime('now'));
        $this->em->persist($validation);
        $this->em->flush();

        return 0;
    }

    /**
     * Reconstructs the arguments as an array of strings
     *
     * @return array[string]
     */
    private function reconstructArgs(Validation $validation)
    {
        $args = [];
        $arguments = $validation->getArguments();

        foreach ($arguments as $key => $value) {
            if (!$value || $value == '' || $value == null) {
                continue;
            }

            if (\strlen($key) > 1) {
                array_push($args, '--' . $key);
            } else {
                array_push($args, '-' . $key);
            }

            if (!is_bool($value)) {
                array_push($args, $value);
            }
        }

        return $args;
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
            'datasetName' => $validation->getDatasetName()
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
            'datasetName' => $validation->getDatasetName()
        ]);
        $fs = new Filesystem();

        $validationDirectory = $this->storage->getDirectory($validation);
        $normDataParentDir = $validationDirectory . '/validation/';
        $datasetName = $validation->getDatasetName();

        // checking if normalized data is present
        if (!$fs->exists($normDataParentDir . $datasetName)) {
            return;
        }

        $process = new Process("(cd $normDataParentDir && zip -r $datasetName.zip $datasetName)");
        $process->setTimeout(600);
        $process->setIdleTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
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
            'datasetName' => $validation->getDatasetName()
        ]);
        $validationDirectory = $this->storage->getDirectory($validation);

        $fs = new FileSystem();
        $sourceDataDir = $validationDirectory . '/' . $validation->getDatasetName();
        if ($fs->exists($sourceDataDir)) {
            $this->logger->debug('Validation[{uid}] : rm -rf {uid}/{datasetName}/...', [
                'uid' => $validation->getUid(),
                'datasetName' => $validation->getDatasetName()
            ]);
            $fs->remove($sourceDataDir);
        }

        // clean uncompressed normalized data
        $normDataDir = $validationDirectory . '/validation/' . $validation->getDatasetName();
        if ($fs->exists($normDataDir)) {
            $this->logger->debug('Validation[{uid}] : rm -rf {uid}/validation/{datasetName}...', [
                'uid' => $validation->getUid(),
                'datasetName' => $validation->getDatasetName()
            ]);
            $fs->remove($normDataDir);
        }

        // clean validation temporary database
        $tempDatabase = $validationDirectory . '/validation/document_database.db';
        if ($fs->exists($tempDatabase)) {
            $this->logger->debug('Validation[{uid}] : rm -f {uid}/validation/document_database.db...', [
                'uid' => $validation->getUid(),
                'datasetName' => $validation->getDatasetName()
            ]);
            $fs->remove($tempDatabase);
        }
    }

}
