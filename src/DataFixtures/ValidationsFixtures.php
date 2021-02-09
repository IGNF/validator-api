<?php

namespace App\DataFixtures;

use App\Entity\Validation;
use App\Service\ValidatorArgumentsService;
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
        $valArgsService = new ValidatorArgumentsService(dirname(__FILE__) . '/../..');

        // a validation with no args
        $validation = new Validation();
        $validation->setDatasetName(str_replace('.zip', '', $this::FILENAME));

        $fs->copy($this::DIR_DATA . "/" . $this::FILENAME, $validation->getDirectory() . "/" . $this::FILENAME);
        $em->persist($validation);

        $this->addReference('validation_no_args', $validation);

        // a validation that has already been archived
        $valArchived = new Validation();
        $valArchived->setDatasetName(str_replace('.zip', '', $this::FILENAME));
        $valArchived->setStatus(Validation::STATUS_ARCHIVED);

        $fs->copy($this::DIR_DATA . "/" . $this::FILENAME, $valArchived->getDirectory() . "/" . $this::FILENAME);
        $em->persist($valArchived);

        $this->addReference('validation_archived', $valArchived);

        // a validation with args
        $args = [
            'srs' => 'EPSG:2154',
            'model' => 'https://ocruze.github.io/fileserver/config/cnig_CC_2017.json',
        ];
        $args = $valArgsService->validate(\json_encode($args));

        $valWithArgs = new Validation();
        $valWithArgs->setDatasetName(str_replace('.zip', '', $this::FILENAME));
        $valWithArgs->setStatus(Validation::STATUS_PENDING);
        $valWithArgs->setArguments($args);

        $fs->copy($this::DIR_DATA . "/" . $this::FILENAME, $valWithArgs->getDirectory() . "/" . $this::FILENAME);
        $em->persist($valWithArgs);

        $this->addReference('validation_with_args', $valWithArgs);

        // a validation with args, the model url argument is wrong and will raise a Java runtime exception which will mark the Symfony process as failed
        $args = [
            'srs' => 'EPSG:2154',
            'model' => 'https://ocruze.github.io/fileserver/config/cnig_CC_2017-test.json',
        ];
        $args = $valArgsService->validate(\json_encode($args));

        $valWithArgs2 = new Validation();
        $valWithArgs2->setDatasetName(str_replace('.zip', '', $this::FILENAME));
        $valWithArgs2->setStatus(Validation::STATUS_PENDING);
        $valWithArgs2->setArguments($args);

        $fs->copy($this::DIR_DATA . "/" . $this::FILENAME, $valWithArgs2->getDirectory() . "/" . $this::FILENAME);
        $em->persist($valWithArgs2);

        $this->addReference('validation_with_args_2', $valWithArgs2);

        $em->flush();
    }
}
