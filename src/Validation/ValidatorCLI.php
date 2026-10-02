<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Exception\ValidatorNotFoundException;
use App\Storage\ValidationsStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Helper class to invoke validator-cli.jar from IGNF/validator.
 */
class ValidatorCLI
{
    /**
     * @var ValidationsStorage
     */
    private $storage;

    /**
     * @var string
     */
    private $validatorPath;

    /**
     * @var string
     */
    private $validatorJavaOpts;

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

    /**
     * Running validator-cli.jar process (to stop it when the worker is stopped).
     */
    private ?Process $currentProcess = null;

    public function __construct(
        ValidationsStorage $storage,
        $validatorPath,
        $validatorJavaOpts,
        $gmlasConfigPath,
        LoggerInterface $logger,
    ) {
        $this->storage = $storage;
        $this->validatorPath = $validatorPath;
        if (!file_exists($this->validatorPath)) {
            throw new ValidatorNotFoundException($this->validatorPath);
        }
        $this->validatorJavaOpts = $validatorJavaOpts;
        $this->gmlasConfigPath = $gmlasConfigPath;
        $this->logger = $logger;
    }

    /**
     * Invoke validator-cli.jar from IGNF/validator on the validation.
     *
     * @return void
     *
     * @throws ProcessFailedException
     */
    public function process(Validation $validation)
    {
        $validationDirectory = $this->storage->getDirectory($validation);

        /*
         * prepare validator-cli.jar command
         * note that Process merges this with the current process' environment,
         * so there is no need to copy $_ENV here.
         */
        $env = ['GMLAS_CONFIG' => $this->gmlasConfigPath];
        /*
         * specify validation schema
         * TODO : compute DB_URL=jdbc:postgresql:${PGDATABASE}, DB_USER et DB_PASSWORD according to doctrine?
         */
        $env['DB_SCHEMA'] = 'validation'.$validation->getUid();

        $sourceDataDir = $validationDirectory.'/'.$validation->getDatasetName();
        $cmd = ['java'];
        // ignore empty options (ex : VALIDATOR_JAVA_OPTS='')
        $cmd = \array_merge($cmd, array_values(array_filter(explode(' ', $this->validatorJavaOpts), fn (string $opt) => '' !== $opt)));
        $cmd = \array_merge($cmd, [
            '-jar', $this->validatorPath,
            'document_validator',
            '--input', $sourceDataDir,
        ]);
        $args = $this->reconstructArgs($validation);
        $cmd = \array_merge($cmd, $args);

        // executing validation program
        $this->logger->info('Validation[{uid}]: executing Java validation program', ['uid' => $validation->getUid()]);
        $process = new Process(
            $cmd,
            $validationDirectory, // note that validator-debug.log is located in current directory,
            $env
        );
        $process->setTimeout(600);
        $process->setIdleTimeout(600);
        $this->currentProcess = $process;
        try {
            $process->run();
        } finally {
            $this->currentProcess = null;
        }

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
        $results = '['.$results.']';
        $results = \json_decode($results, true);

        $validation->setResults(is_array($results) ? $results : null);

        /*
         * read document info, only produced when the "normalize" argument is enabled
         */
        $documentInfoPath = $validationDirectory.'/validation/document-info.json';
        if (file_exists($documentInfoPath)) {
            $documentInfo = \json_decode(\file_get_contents($documentInfoPath), true);
            $validation->setDocumentInfo(is_array($documentInfo) ? $documentInfo : null);
        }
    }

    /**
     * Stops the running validator-cli.jar process, if any (invoked when the worker receives SIGTERM).
     */
    public function stop(): void
    {
        if (null !== $this->currentProcess && $this->currentProcess->isRunning()) {
            $this->logger->warning('stopping validator-cli.jar process (pid={pid})', [
                'pid' => $this->currentProcess->getPid(),
            ]);
            $this->currentProcess->stop(10);
        }
    }

    /**
     * Reconstructs the arguments as an array of strings.
     *
     * @return array[string]
     */
    private function reconstructArgs(Validation $validation)
    {
        $args = [];
        $arguments = $validation->getArguments();

        foreach ($arguments as $key => $value) {
            // false : flag disabled, null or '' : option not set (0 is a valid value, ex : max-errors)
            if (false === $value || null === $value || '' === $value) {
                continue;
            }

            if (\strlen($key) > 1) {
                array_push($args, '--'.$key);
            } else {
                array_push($args, '-'.$key);
            }

            if (!is_bool($value)) {
                array_push($args, (string) $value);
            }
        }

        return $args;
    }
}
