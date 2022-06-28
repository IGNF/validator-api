<?php

namespace App\Tests\Validation;

use App\Tests\WebTestCase;
use App\Validation\ZipArchiveValidator;
use Psr\Log\NullLogger;

class ZipArchiveValidatorTest extends WebTestCase
{

    /**
     * @var ZipArchiveValidator
     */
    private $zipArchiveValidator;

    public function setUp(): void
    {
        // to debug : new Logger("test")
        $this->zipArchiveValidator = new ZipArchiveValidator(new NullLogger());
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * All the files in the zip have valid names
     */
    public function testValidZip()
    {
        $errors = $this->zipArchiveValidator->validate(__DIR__ . '/../../../tests/data/130010853_PM3_60_20180516.zip');
        $this->assertEmpty($errors);
    }

    /**
     * empty zip
     */
    public function testEmptyZip()
    {
        $zipPath = __DIR__ . '/../../../tests/data/empty.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertIsArray($errors);
        $this->assertEquals(1, count($errors));
        $this->assertEquals($zipName, $errors[0]['file']);
        $this->assertEquals(sprintf("Archive %s is empty", $zipName), $errors[0]['message']);
        $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
    }

    /**
     * impossible to open zip
     */
    public function testImpossibleToOpenZip()
    {
        $zipPath = __DIR__ . '/../../../tests/data/corrupted.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertIsArray($errors);
        $this->assertEquals(1, count($errors));

        $this->assertEquals($zipName, $errors[0]['file']);
        $this->assertEquals(sprintf("Impossible to open archive %s", $zipName), $errors[0]['message']);
        $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
    }

    /**
     * zip doesn't exist
     */
    public function testZipDoesntExist()
    {
        $zipPath = __DIR__ . '/../../../tests/data/doesnt-exist.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertIsArray($errors);
        $this->assertEquals(1, count($errors));

        $this->assertEquals($zipName, $errors[0]['file']);
        $this->assertEquals(sprintf("The zip archive file %s doesn't exist", $zipName), $errors[0]['message']);
        $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
    }

    /**
     * zip contains files with invalid name (doesn't match regex)
     */
    public function testZipFilesInvalidName()
    {
        $zipPath = __DIR__ . '/../../../tests/data/130010853_PM3_60_20180516-invalid-regex.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertIsArray($errors);
        $this->assertEquals(2, count($errors));

        foreach ($errors as $error) {
            $this->assertStringContainsStringIgnoringCase("filename is not valid", $error['message']);
            $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE_FILENAME, $error['code']);
        }
    }
}
