<?php

namespace App\Tests\Validation;

use App\Exception\ZipArchiveValidationException;
use App\Tests\WebTestCase;
use App\Validation\ZipArchiveValidator;
use Monolog\Logger;

class ZipArchiveValidatorTest extends WebTestCase
{

    /**
     * @var ZipArchiveValidator
     */
    private $zipArchiveValidator;

    public function setUp(): void
    {
        $logger = new Logger("test");
        $this->zipArchiveValidator = new ZipArchiveValidator($logger);
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
        $errors = $this->zipArchiveValidator->validate(__DIR__ . '/../Data/130010853_PM3_60_20180516.zip');
        $this->assertNull($errors);
    }

    /**
     * empty zip
     */
    public function testEmptyZip()
    {
        $thrown = false;
        $zipPath = __DIR__ . '/../Data/empty.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        try {
            $this->zipArchiveValidator->validate($zipPath);
        } catch (ZipArchiveValidationException $ex) {
            $thrown = true;
            $this->assertInstanceOf(ZipArchiveValidationException::class, $ex);

            $errors = $ex->getErrors();
            $this->assertIsArray($errors);
            $this->assertEquals(1, count($errors));
            $this->assertEquals("Zip archive pre-validation failed", $ex->getMessage());

            $this->assertEquals($zipName, $errors[0]['file']);
            $this->assertEquals(sprintf("Archive %s is empty", $zipName), $errors[0]['message']);
            $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
        }

        $this->assertTrue($thrown, sprintf("%s was expected to be thrown", ZipArchiveValidationException::class));
    }

    /**
     * impossible to open zip
     */
    public function testImpossibleToOpenZip()
    {
        $thrown = false;
        $zipPath = __DIR__ . '/../Data/corrupted.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        try {
            $this->zipArchiveValidator->validate($zipPath);
        } catch (ZipArchiveValidationException $ex) {
            $thrown = true;
            $this->assertInstanceOf(ZipArchiveValidationException::class, $ex);

            $errors = $ex->getErrors();
            $this->assertIsArray($errors);
            $this->assertEquals(1, count($errors));
            $this->assertEquals("Zip archive pre-validation failed", $ex->getMessage());

            $this->assertEquals($zipName, $errors[0]['file']);
            $this->assertEquals(sprintf("Impossible to open archive %s", $zipName), $errors[0]['message']);
            $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
        }

        $this->assertTrue($thrown, sprintf("%s was expected to be thrown", ZipArchiveValidationException::class));
    }

    /**
     * zip doesn't exist
     */
    public function testZipDoesntExist()
    {
        $thrown = false;
        $zipPath = __DIR__ . '/../Data/doesnt-exist.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        try {
            $this->zipArchiveValidator->validate($zipPath);
        } catch (ZipArchiveValidationException $ex) {
            $thrown = true;
            $this->assertInstanceOf(ZipArchiveValidationException::class, $ex);

            $errors = $ex->getErrors();
            $this->assertIsArray($errors);
            $this->assertEquals(1, count($errors));
            $this->assertEquals("Zip archive pre-validation failed", $ex->getMessage());

            $this->assertEquals($zipName, $errors[0]['file']);
            $this->assertEquals(sprintf("The zip archive file %s doesn't exist", $zipName), $errors[0]['message']);
            $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
        }

        $this->assertTrue($thrown, sprintf("%s was expected to be thrown", ZipArchiveValidationException::class));
    }

    /**
     * zip contains files with invalid name (doesn't match regex)
     */
    public function testZipFilesInvalidName()
    {
        $thrown = false;
        $zipPath = __DIR__ . '/../Data/130010853_PM3_60_20180516-invalid-regex.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        try {
            $this->zipArchiveValidator->validate($zipPath);
        } catch (ZipArchiveValidationException $ex) {
            $thrown = true;
            $this->assertInstanceOf(ZipArchiveValidationException::class, $ex);

            $errors = $ex->getErrors();
            $this->assertIsArray($errors);
            $this->assertEquals(2, count($errors));
            $this->assertEquals("Zip archive pre-validation failed", $ex->getMessage());

            foreach ($errors as $error) {
                $this->assertStringContainsStringIgnoringCase("filename is not valid", $error['message']);
                $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE_FILENAME, $error['code']);
            }
        }

        $this->assertTrue($thrown, sprintf("%s was expected to be thrown", ZipArchiveValidationException::class));
    }
}
