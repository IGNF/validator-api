<?php

namespace App\Command\Validations;

use App\Validation\ValidationManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper command to process pending validations.
 */
class ProcessOneCommand extends Command implements SignalableCommandInterface
{
    protected static $defaultName = 'ign-validator:validations:process-one';

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
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->validationManager = $validationManager;
        $this->logger = $logger;
    }

    protected function configure()
    {
        // TODO add --uid option to ease command testing
        $this
            ->setDescription('Launches a document validation')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validationManager->processOne();
        return 0;
    }

    public function getSubscribedSignals(): array
    {
        return [\SIGINT,\SIGTERM];
    }

    public function handleSignal(int $signal): void
    {
        $this->logger->warning("[ProcessOneCommand] received stop signal while processing validation!",[
            'signal' => $signal
        ]);
        $this->validationManager->cancelProcessing();
        $exitCode = 128 + $signal;
        $this->logger->info("terminate process with exitCode={exitCode}",[
            'exitCode' => $exitCode
        ]);
        exit($exitCode);
    }
}

