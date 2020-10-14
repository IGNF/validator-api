<?php

namespace App\Command;

use App\Entity\Validation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DeleteOldFilesCommand extends Command
{
    protected static $defaultName = 'app:delete-old-files';

    /**
     * Time interval of 30 days
     */
    const EXPIRY_CONDITION = 'P1M';

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setDescription('Deletes all validation files that were created 30 days ago')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTime('now');
        $dateExpire = $today->sub(new \DateInterval($this::EXPIRY_CONDITION));

        $repository = $this->em->getRepository(Validation::class);
        $validations = $repository->findAllToBeArchived($dateExpire->format('Y-m-d'));

        $filesystem = new FileSystem();

        foreach ($validations as $validation) {
            $directory = $validation->getDirectory();

            if ($filesystem->exists($directory)) {
                $filesystem->remove($directory);
            }

            $validation->setStatus(Validation::STATUS_ARCHIVED);
            $this->em->persist($validation);
            $this->em->flush();
        }

        return 0;
    }
}
