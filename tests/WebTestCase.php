<?php

namespace App\Tests;

use App\Storage\ValidationsStorage;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Description of GcmsTestCase
 *
 * @author RPas
 */
abstract class WebTestCase extends BaseWebTestCase
{
    /**
     *
     * @var AbstractExecutor
     */
    protected $fixtures;

    /**
     * Retourne les fixtures
     * @return ReferenceRepository
     */
    public function getFixtures()
    {
        return $this->fixtures;
    }

    /**
     * Retourne la référence d'une fixture
     * @param string $name Nom de la référence
     * @return mixed (souvent une entité)
     * @throws \Exception
     */
    public function getReference($name)
    {
        if ($this->fixtures->getReferenceRepository()->hasReference($name)) {
            return $this->fixtures->getReferenceRepository()->getReference($name);
        } else {
            throw new \Exception("No reference found for $name");
        }
    }


    /**
     * @return ValidationsStorage
     */
    public function getValidationsStorage(){
        return $this->getContainer()->get(ValidationsStorage::class);
    }

    /**
     * Create a temp directory.
     *
     * @param string $prefix
     *
     * @return string
     */
    protected function createTempDirectory($prefix = '')
    {
        $path = sys_get_temp_dir().'/'.uniqid($prefix);
        $this->assertTrue(mkdir($path));

        return $path;
    }


    /**
     * Create a fake UploadedFile with a copy of a sample file in tests/Data directory     *
     * @param string $filename
     * @param string $mineType
     * @return UploadedFile
     */
    protected function createFakeUpload($filename,$mineType='application/zip'){
        $samplePath = __DIR__.'/Data/'.$filename;
        $this->assertFileExists($samplePath);

        $tempDirectory = $this->createTempDirectory('upload-');
        $fs = new Filesystem();
        $fs->copy($samplePath, $tempDirectory.'/'.$filename);
        return new UploadedFile(
            $tempDirectory.'/'.$filename,
            $filename,
            $mineType,
            null,
            true
        );
    }

}