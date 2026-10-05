<?php

namespace App\Command\Validations;

use App\Repository\ValidationRepository;
use App\Validation\ValidationManager;
use DateInterval;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper command to archive old validations removing files.
 */
#[AsCommand(name: 'ign-validator:validations:cleanup', description: 'Deletes all validation files that are older than max-age (default 5 days)')]
class CleanupCommand extends Command
{
    /**
     * Time interval of 5 days.
     */
    public const DEFAULT_EXPIRY_CONDITION = 'P5D';

    /**
     * Validations still processing after the validator-cli.jar timeout (VALIDATOR_TIMEOUT) plus this margin
     * are considered as interrupted (worker killed, out of memory...), with at least MIN_PROCESSING_TIMEOUT.
     */
    public const PROCESSING_TIMEOUT_MARGIN = 600;
    public const MIN_PROCESSING_TIMEOUT = 3600;

    /**
     * @var ValidationManager
     */
    private $validationManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        private ValidationRepository $validationRepository,
        ValidationManager $validationManager,
        LoggerInterface $logger,
        // max duration of validator-cli.jar in seconds (VALIDATOR_TIMEOUT)
        private int $validatorTimeout = 1800,
    ) {
        parent::__construct();
        $this->validationManager = $validationManager;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'max-age',
                null,
                InputOption::VALUE_REQUIRED,
                'Max duration to keep validation files (P1M : 1 month, P5D : 5 jours, PT30M : 30 minutes,...)',
                self::DEFAULT_EXPIRY_CONDITION
            )
            ->addOption(
                'processing-timeout',
                null,
                InputOption::VALUE_REQUIRED,
                'Validations still processing after this duration are marked as failed (worker killed, out of memory...), default : VALIDATOR_TIMEOUT + 10 minutes, at least 1 hour',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interrupted = $this->markInterrupted($input->getOption('processing-timeout') ?? $this->getDefaultProcessingTimeout());
        $output->writeln(sprintf('%d interrupted validation(s) marked as failed.', $interrupted));

        $maxAge = $input->getOption('max-age');
        $today = new DateTime('now');
        $dateExpire = $today->sub(new DateInterval($maxAge));

        $this->logger->info('archive validations older than {$maxAge}...', [
            '$maxAge' => $maxAge,
        ]);
        $validations = $this->validationRepository->findAllToBeArchived($dateExpire);
        $count = 0;
        $failures = 0;
        foreach ($validations as $validation) {
            // an error on a validation (ex : storage) must not prevent the others from being archived
            try {
                $this->validationManager->archive($validation);
                ++$count;
            } catch (\Throwable $th) {
                ++$failures;
                $this->logger->error('Validation[{uid}] : fail to archive', [
                    'uid' => $validation->getUid(),
                    'exception' => $th,
                ]);
            }
        }
        $this->logger->info('archive validations older than {maxTime} : completed, {count} validation(s) processed.', [
            'maxTime' => $maxAge,
            'count' => $count,
            'failures' => $failures,
        ]);
        $output->writeln(sprintf('%d validation(s) archived.', $count));
        if ($failures > 0) {
            $output->writeln(sprintf('<error>%d validation(s) could not be archived.</error>', $failures));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Default processing timeout (ISO 8601 duration), longer than the validator-cli.jar timeout
     * (a long validation must not be marked as failed while it is still running).
     */
    public function getDefaultProcessingTimeout(): string
    {
        return sprintf('PT%dS', max(self::MIN_PROCESSING_TIMEOUT, $this->validatorTimeout + self::PROCESSING_TIMEOUT_MARGIN));
    }

    /**
     * Marks as failed the validations still processing after processingTimeout.
     */
    private function markInterrupted(string $processingTimeout): int
    {
        $maxDateStart = (new DateTime('now'))->sub(new DateInterval($processingTimeout));
        $validations = $this->validationRepository->findAllInterrupted($maxDateStart);
        foreach ($validations as $validation) {
            $this->validationManager->markInterrupted($validation);
        }

        return count($validations);
    }
}
