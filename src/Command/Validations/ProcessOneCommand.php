<?php

namespace App\Command\Validations;

use App\Validation\ValidationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper command to process pending validations.
 */
class ProcessOneCommand extends Command
{
    protected static $defaultName = 'ign-validator:validations:process-one';

    /**
     * @var ValidationManager
     */
    private $validationManager;

    public function __construct(
        ValidationManager $validationManager
    ) {
        parent::__construct();
        $this->validationManager = $validationManager;
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

}
