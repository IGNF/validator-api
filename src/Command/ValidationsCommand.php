<?php

namespace App\Command;

use App\Entity\Validation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ValidationsCommand extends Command
{
    protected static $defaultName = 'app:validations';

    const VALIDATOR_PATH = './validator-cli.jar';

    private $validation;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em)
    {
        parent::__construct();
        $this->params = $params;
        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setDescription('Launches a document validation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $repository = $this->em->getRepository(Validation::class);

        $this->validation = $repository->findOneByStatus(Validation::STATUS_PENDING);
        

        return 0;
    }
}
