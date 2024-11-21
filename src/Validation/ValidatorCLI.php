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

    public function __construct(
        ValidationsStorage $storage,
        $validatorPath,
        $validatorJavaOpts,
        $gmlasConfigPath,
        LoggerInterface $logger
    ) {
        $this->storage = $storage;
        $this->validatorPath = $validatorPath;
        if ( ! file_exists($this->validatorPath) ){
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

        /* prepare validator-cli.jar command */
        $env = $_ENV;
        $env['GMLAS_CONFIG'] = $this->gmlasConfigPath;

        
        $sourceDataDir = $validationDirectory.'/'.$validation->getDatasetName();
        $cmd = ['java'];
        $cmd = \array_merge($cmd, explode(' ',$this->validatorJavaOpts));
        $cmd = \array_merge($cmd,[
            '-jar', $this->validatorPath,
            'document_validator',
            '--input', $sourceDataDir
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
        $results = '['.$results.']';
        $results = \json_decode($results, true);

        $validation->setResults($results);
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
            if (!$value || '' == $value || null == $value) {
                continue;
            }

            if (\strlen($key) > 1) {
                array_push($args, '--'.$key);
            } else {
                array_push($args, '-'.$key);
            }

            if (!is_bool($value)) {
                array_push($args, $value);
            }
        }

        return $args;
    }
}
