<?php

namespace App\Command\Validations;

use App\Entity\Validation;
use App\Repository\ValidationRepository;
use App\Validation\ValidationManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper command to process pending validations.
 */
class ProcessOneCommand extends Command
{
    protected static $defaultName = 'app:validations:process-one';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ValidationManager
     */
    private $validationManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        EntityManagerInterface $em,
        ValidationManager $validationManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->em = $em;
        $this->validationManager = $validationManager;
        $this->logger = $logger;
    }

    protected function configure()
    {
        // TODO add --uid option to ease command testing
        $this
            ->setDescription('Launches a document validation');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $validation = $this->getValidationRepository()->popNextPending();
        if (is_null($validation)) {
            $this->logger->info("Validation[null]: no validation pending, quitting");
            return 0;
        }
        $this->validationManager->process($validation);
        return 0;
    }

    /**
     * @return ValidationRepository
     */
    protected function getValidationRepository()
    {
        return $this->em->getRepository(Validation::class);
    }
}
