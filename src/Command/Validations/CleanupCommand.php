<?php

namespace App\Command\Validations;

use App\Entity\Validation;
use App\Repository\ValidationRepository;
use App\Validation\ValidationManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper command to archive old validations removing files.
 */
class CleanupCommand extends Command
{
    protected static $defaultName = 'app:validations:cleanup';

    /**
     * Time interval of 30 days
     */
    const DEFAULT_EXPIRY_CONDITION = 'P1M';

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
        $this
            ->setDescription('Deletes all validation files that were created before max-time duration (default 1 month)')
            ->addOption(
                'max-time',
                null,
                InputOption::VALUE_REQUIRED,
                'Max duration to keep validation files (P1M : 1 month, PT30M : 30 minutes,...)',
                self::DEFAULT_EXPIRY_CONDITION
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maxTime = $input->getOption('max-time');
        $today = new \DateTime('now');
        $dateExpire = $today->sub(new \DateInterval($maxTime));

        $this->logger->info('archive validations older than {$maxTime}...', [
            '$maxTime' => $maxTime
        ]);
        $validations = $this->getValidationRepository()->findAllToBeArchived($dateExpire);
        $count = 0;
        foreach ($validations as $validation) {
            $this->validationManager->archive($validation);
            $count++;
        }
        $this->logger->info('archive validations older than {maxTime} : completed, {count} validation(s) processed.', [
            'maxTime' => $maxTime,
            'count' => $count
        ]);
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
