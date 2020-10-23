<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestCommand extends Command
{
    protected static $defaultName = 'app:test';

    protected function configure()
    {
        $this
            ->setDescription('Add a short description for your command')

        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        for ($i = 0; $i < 20; $i++) {
            $io->writeln(\uniqid());
        }

        return 0;
    }
}
