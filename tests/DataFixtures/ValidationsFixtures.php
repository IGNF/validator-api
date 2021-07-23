<?php

namespace App\Tests\DataFixtures;

use App\Entity\Validation;
use App\Service\ValidatorArgumentsService;
use App\Storage\ValidationsStorage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class ValidationsFixtures extends Fixture
{
    const FILENAME_SUP_PM3 = "130010853_PM3_60_20180516.zip";

    /**
     * @var ValidatorArgumentsService
     */
    private $valArgsService;

    /**
     * @var ValidationsStorage
     */
    private $validationsStorage;

    public function __construct(
        ValidatorArgumentsService $valArgsService,
        ValidationsStorage $validationsStorage
    )
    {
        $this->valArgsService = $valArgsService;
        $this->validationsStorage = $validationsStorage;
    }

    /**
     * Add sample archive to the storage
     *
     * @param Validation $validation
     * @param string $filename
     * @return void
     */
    private function addSampleArchive(Validation $validation,$filename){
        $originalPath = __DIR__.'/../Data/'.$filename;
        if (! file_exists($originalPath) ){
            throw new RuntimeException('Sample file not found : '.$originalPath);
        }

        $validationDirectory = $this->validationsStorage->getDirectory($validation);
        $fs = new Filesystem();
        $validation->setDatasetName(str_replace('.zip', '', $filename));
        $fs->copy(
            $originalPath,
            $validationDirectory . "/" . $filename
        );
    }

    public function load(ObjectManager $em)
    {
        $fs = new Filesystem();

        /*
         * validation_no_args - a validation with no args
         */
        $validationNoArgs = new Validation();
        $this->addSampleArchive($validationNoArgs,self::FILENAME_SUP_PM3);
        $em->persist($validationNoArgs);
        $this->addReference('validation_no_args', $validationNoArgs);

        /*
         * validation_archived - a validation that has already been archived
         * (no file, archived)
         */
        $valArchived = new Validation();
        $valArchived->setDatasetName('130010853_PM3_60_20180516');
        $valArchived->setStatus(Validation::STATUS_ARCHIVED);
        $em->persist($valArchived);
        $this->addReference('validation_archived', $valArchived);

        /*
         * validation_with_args - a validation where args are provided
         */
        $args = [
            'srs' => 'EPSG:2154',
            'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json',
        ];
        $args = $this->valArgsService->validate(\json_encode($args));

        $valWithArgs = new Validation();
        $this->addSampleArchive($valWithArgs,self::FILENAME_SUP_PM3);
        $valWithArgs->setStatus(Validation::STATUS_PENDING);
        $valWithArgs->setArguments($args);
        $em->persist($valWithArgs);
        $this->addReference('validation_with_args', $valWithArgs);

        /*
         * validation_with_bad_args - a validation with bad args
         * (the model url argument is wrong and will raise a Java runtime exception which will mark the Symfony process as failed)
         */
        $args = [
            'srs' => 'EPSG:2154',
            'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016-test.json',
        ];
        $args = $this->valArgsService->validate(\json_encode($args));

        $valWithBadArgs = new Validation();
        $this->addSampleArchive($valWithBadArgs,self::FILENAME_SUP_PM3);
        $valWithBadArgs->setStatus(Validation::STATUS_PENDING);
        $valWithBadArgs->setArguments($args);
        $em->persist($valWithBadArgs);
        $this->addReference('validation_with_bad_args', $valWithBadArgs);

        $em->flush();
    }
}
