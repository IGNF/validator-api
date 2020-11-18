<?php

namespace App\Command;

use App\Service\ValidatorArgumentsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ArgsConfigCheckCommand extends Command
{
    protected static $defaultName = 'app:args-config-check';

    private $varlArgsService;

    public function __construct(ValidatorArgumentsService $varlArgsService)
    {
        parent::__construct();
        $this->varlArgsService = $varlArgsService;
    }

    protected function configure()
    {
        $this
            ->setDescription('Checks the config of validator arguments in the config file at ressources/validator-arguments.json')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->varlArgsService->load();
            $this->varlArgsService->checkConfig();
            $io->success('Arguments configuration is correct');
        } catch (\Throwable $th) {
            $io->error($th->getMessage());
            return 1;
        }

        return 0;
    }
}
