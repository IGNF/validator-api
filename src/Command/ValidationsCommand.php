<?php

namespace App\Command;

use App\Entity\Validation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ZipArchive;

class ValidationsCommand extends Command
{
    protected static $defaultName = 'app:validations';

    const VALIDATOR_PATH = './validator-cli.jar';

    private $validation;

    private $em;
    private $logger;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, LoggerInterface $logger)
    {
        parent::__construct();
        $this->params = $params;
        $this->em = $em;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setDescription('Launches a document validation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // fetch one pending validation
        $repository = $this->em->getRepository(Validation::class);
        $this->validation = $repository->findNextPending();

        // exit if no pending validation found
        if (!$this->validation) {
            $this->logger->info("Validation[null]: no validation pending, quitting");
            return 0;
        }
        $this->logger->info("Validation[{uid}]: pending validation found", ['uid' => $this->validation->getUid()]);

        $this->validation->setStatus(Validation::STATUS_PROCESSING);
        $this->validation->setDateStart(new \DateTime('now'));
        $this->em->persist($this->validation);
        $this->em->flush();

        try {
            // preparing shell command
            $args = $this->reconstructArgs();
            $datasetDir = $this->validation->getDirectory() . '/' . $this->validation->getDatasetName();

            $cmd = ['java', '-jar', $this::VALIDATOR_PATH, 'document_validator', '--input', $datasetDir];
            $cmd = \array_merge($cmd, $args);

            // decompress dataset
            $this->unzip();

            // executing validation program
            $this->logger->info("Validation[{uid}]: executing Java validation program", ['uid' => $this->validation->getUid()]);
            $process = new Process($cmd);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // fetching validation results
            $results = \file_get_contents(\sprintf("%s/validation/validation.jsonl", $this->validation->getDirectory()));

            // jsonl to json_array
            $results = \str_replace("}\n{", "},\n{", $results);
            $results = "[" . $results . "]";

            $this->validation->setResults($results);

            // finalization
            $this->zipNormData();

            $this->validation->setStatus(Validation::STATUS_FINISHED);

            $this->logger->info("Validation[{uid}]: validation carried out successfully", ['uid' => $this->validation->getUid()]);

        } catch (\Throwable $th) {
            $this->validation->setStatus(Validation::STATUS_ERROR);
            $this->validation->setMessage($th->getMessage());
            $this->logger->error("Validation[{uid}]: {message}", ['uid' => $this->validation->getUid(), 'message' => $th->getMessage()]);
        }

        $this->validation->setDateFinish(new \DateTime('now'));
        $this->em->persist($this->validation);
        $this->em->flush();

        return 0;
    }

    /**
     * Reconstructs the arguments as an array of strings
     *
     * @return array[string]
     */
    private function reconstructArgs()
    {
        $args = [];
        $arguments = $this->validation->getArguments();

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
     * @return void
     * @throws Exception
     */
    private function unzip()
    {
        $zipFilename = $this->validation->getDirectory() . '/' . $this->validation->getDatasetName() . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipFilename) === true) {
            $zip->extractTo($this->validation->getDirectory() . '/' . $this->validation->getDatasetName());
            $zip->close();
        } else {
            throw new \Exception("Zip decompression failed");
        }
    }

    /**
     * Zips the generated normalized data
     *
     * @return void
     * @throws Exception
     */
    private function zipNormData()
    {
        $filesystem = new Filesystem();
        $finder = new Finder();

        $dataDir = $this->validation->getDirectory() . '/validation/' . $this->validation->getDatasetName();

        if (!$filesystem->exists($dataDir)) {
            return;
        }

        $zip = new \ZipArchive();
        $zip->open($this->validation->getDirectory() . '/validation/' . $this->validation->getDatasetName() . '.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $finder->files()->in($dataDir);

        foreach ($finder as $file) {
            $fileNameWithExtension = $file->getRelativePathname();
            $zip->addFromString($fileNameWithExtension, $file->getContents());

        }

        $zip->close();
    }
}
