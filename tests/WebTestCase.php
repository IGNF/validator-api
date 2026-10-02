<?php

namespace App\Tests;

use App\Entity\Validation;
use App\Storage\ValidationsStorage;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Liip\FunctionalTestBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Base helper class to write tests.
 */
abstract class WebTestCase extends BaseWebTestCase
{
    /**
     * Removes the files written to the storage (see flysystem.yaml, when@test).
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->remove(dirname(__DIR__).'/var/data-test');
    }

    /**
     * @var AbstractExecutor
     */
    protected $fixtures;

    /**
     * Retourne la référence d'une fixture.
     *
     * @param string $name Nom de la référence
     *
     * @return mixed (souvent une entité)
     *
     * @throws \Exception
     */
    protected function getValidationFixture($name): Validation
    {
        if ($this->fixtures->getReferenceRepository()->hasReference($name, Validation::class)) {
            return $this->fixtures->getReferenceRepository()->getReference($name, Validation::class);
        } else {
            throw new \Exception("No reference found for $name");
        }
    }

    /**
     * @return ValidationsStorage
     */
    protected function getValidationsStorage()
    {
        return $this->getContainer()->get(ValidationsStorage::class);
    }

    /**
     * Create a zip archive in a temp directory.
     *
     * @param array<string,string> $files content by entry name (names ending with "/" are directories)
     *
     * @return string path to the zip archive
     */
    protected function createZip(array $files, string $zipName = 'test.zip'): string
    {
        $zipPath = $this->createTempDirectory('zip-').'/'.$zipName;

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE));
        foreach ($files as $name => $content) {
            if (str_ends_with($name, '/')) {
                $zip->addEmptyDir($name);
            } else {
                $zip->addFromString($name, $content);
            }
        }
        $this->assertTrue($zip->close());

        return $zipPath;
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
     * Get tests/data folder path.
     *
     * @return string
     */
    protected function getTestDataDir()
    {
        return __DIR__.'/data/';
    }

    /**
     * Create a fake UploadedFile with a copy of a sample file in tests/Data directory     *.
     *
     * @param string $filename
     * @param string $mineType
     *
     * @return UploadedFile
     */
    protected function createFakeUpload($filename, $mineType = 'application/zip')
    {
        $samplePath = $this->getTestDataDir().'/'.$filename;
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
