<?php

namespace App\DataFixtures;

use App\Entity\Validation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Filesystem\Filesystem;

class ValidationsFixtures extends Fixture
{
    const FILENAME = "130010853_PM3_60_20180516.zip";
    const DIR_DATA = './tests/Data';
    const DIR_DATA_TEMP = './tests/TempData';

    public function load(ObjectManager $em)
    {
        $fs = new Filesystem();

        $validation = new Validation();
        $validation->setDatasetName(str_replace('.zip', '', $this::FILENAME));
        $fs->copy($this::DIR_DATA . "/" . $this::FILENAME, $validation->getDirectory() . "/" . $this::FILENAME);
        $em->persist($validation);

        $this->addReference('validation_1', $validation);

        $valArchived = new Validation();
        $valArchived->setDatasetName(str_replace('.zip', '', $this::FILENAME));
        $valArchived->setStatus(Validation::STATUS_ARCHIVED);
        $fs->copy($this::DIR_DATA . "/" . $this::FILENAME, $valArchived->getDirectory() . "/" . $this::FILENAME);
        $em->persist($valArchived);

        $this->addReference('validation_archived', $valArchived);

        $em->flush();
    }
}
