<?php

namespace App\Command\Validations;

use App\Validation\ValidationManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper command to process pending validations.
 */
#[AsCommand(name: 'ign-validator:validations:process-one', description: 'Launches a document validation')]
class ProcessOneCommand extends Command implements SignalableCommandInterface
{
    /**
     * @var ValidationManager
     */
    private $validationManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ValidationManager $validationManager,
        LoggerInterface $logger,
    ) {
        parent::__construct();
        $this->validationManager = $validationManager;
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validationManager->processOne();

        return 0;
    }

    public function getSubscribedSignals(): array
    {
        return [\SIGINT, \SIGTERM];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->logger->warning('[ProcessOneCommand] received stop signal while processing validation!', [
            'signal' => $signal,
        ]);
        $this->validationManager->cancelProcessing();
        $exitCode = 128 + $signal;
        $this->logger->info('terminate process with exitCode={exitCode}', [
            'exitCode' => $exitCode,
        ]);
        exit($exitCode);
    }
}
