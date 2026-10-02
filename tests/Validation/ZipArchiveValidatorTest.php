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
     * All the files in the zip have valid names.
     */
    public function testValidZip()
    {
        $archivePath = $this->getTestDataDir().'/130010853_PM3_60_20180516.zip';
        $this->assertFileExists($archivePath);
        $errors = $this->zipArchiveValidator->validate($archivePath);
        $this->assertEmpty($errors);
    }

    /**
     * empty zip.
     */
    public function testEmptyZip()
    {
        $zipPath = $this->getTestDataDir().'/empty.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertIsArray($errors);
        $this->assertEquals(1, count($errors));
        $this->assertEquals($zipName, $errors[0]['file']);
        $this->assertEquals(sprintf('Archive %s is empty', $zipName), $errors[0]['message']);
        $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
    }

    /**
     * impossible to open zip.
     */
    public function testImpossibleToOpenZip()
    {
        $zipPath = $this->getTestDataDir().'/corrupted.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertIsArray($errors);
        $this->assertEquals(1, count($errors));

        $this->assertEquals($zipName, $errors[0]['file']);
        $this->assertEquals(sprintf('Impossible to open archive %s', $zipName), $errors[0]['message']);
        $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
    }

    /**
     * zip doesn't exist.
     */
    public function testZipDoesntExist()
    {
        $zipPath = $this->getTestDataDir().'/doesnt-exist.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertIsArray($errors);
        $this->assertEquals(1, count($errors));

        $this->assertEquals($zipName, $errors[0]['file']);
        $this->assertEquals(sprintf("The zip archive file %s doesn't exist", $zipName), $errors[0]['message']);
        $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE, $errors[0]['code']);
    }

    /**
     * zip contains files with invalid name (doesn't match regex).
     */
    public function testZipFilesInvalidName()
    {
        $zipPath = $this->getTestDataDir().'/130010853_PM3_60_20180516-invalid-regex.zip';
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertIsArray($errors);
        $this->assertEquals(2, count($errors));

        foreach ($errors as $error) {
            $this->assertStringContainsStringIgnoringCase('filename is not valid', $error['message']);
            $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE_FILENAME, $error['code']);
        }
    }

    /**
     * zip contains executables, scripts or nested archives.
     */
    public function testZipFilesExtensionNotAllowed()
    {
        $zipPath = $this->createZip([
            'data/table.dbf' => "\x03",
            'data/run.exe' => 'MZ',
            'data/run.sh' => '#!/bin/sh',
            'data/nested.zip' => "PK\x03\x04",
            'data/README' => 'no extension',
        ]);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertEquals(
            ['data/run.exe', 'data/run.sh', 'data/nested.zip', 'data/README'],
            array_column($errors, 'file')
        );
        foreach ($errors as $error) {
            $this->assertEquals(ZipArchiveValidator::ERROR_FILE_EXTENSION_NOT_ALLOWED, $error['code']);
        }
    }

    /**
     * extensions are not case sensitive and OS metadata entries are ignored.
     */
    public function testZipFilesAllowed()
    {
        $zipPath = $this->createZip([
            'data/TABLE.DBF' => "\x03",
            'data/.DS_Store' => 'mac',
            '__MACOSX/data/._TABLE.DBF' => 'mac',
            'data/Thumbs.db' => 'windows',
        ]);

        $this->assertEmpty($this->zipArchiveValidator->validate($zipPath));
    }

    /**
     * zip contains entries which would be extracted outside the target directory.
     */
    public function testZipPathTraversal()
    {
        $zipPath = $this->createZip([
            '../evil.csv' => 'a',
            '/tmp/evil.csv' => 'a',
            'data/../../evil.csv' => 'a',
        ]);

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertEquals(3, count($errors));
        foreach ($errors as $error) {
            $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE_ENTRY, $error['code']);
            $this->assertStringStartsWith('path is not allowed', $error['message']);
        }
    }

    /**
     * zip contains a symbolic link.
     */
    public function testZipSymlink()
    {
        $zipPath = $this->createZip(['data/link.csv' => '/etc/passwd']);
        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $zip->setExternalAttributesName('data/link.csv', \ZipArchive::OPSYS_UNIX, 0o120777 << 16);
        $zip->close();

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertEquals(1, count($errors));
        $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE_ENTRY, $errors[0]['code']);
        $this->assertStringStartsWith('symbolic links are not allowed', $errors[0]['message']);
    }

    /**
     * zip contains an encrypted file.
     */
    public function testZipEncrypted()
    {
        $zipPath = $this->createZip(['data/table.csv' => 'a,b']);
        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $zip->setEncryptionName('data/table.csv', \ZipArchive::EM_AES_256, 'secret');
        $zip->close();

        $errors = $this->zipArchiveValidator->validate($zipPath);

        $this->assertEquals(1, count($errors));
        $this->assertEquals(ZipArchiveValidator::ERROR_BAD_ARCHIVE_ENTRY, $errors[0]['code']);
        $this->assertStringStartsWith('encrypted files are not allowed', $errors[0]['message']);
    }

    /**
     * zip bomb protection : number of entries.
     */
    public function testZipTooManyEntries()
    {
        $validator = new ZipArchiveValidator(new NullLogger(), maxEntries: 2);
        $zipPath = $this->createZip(['a.csv' => 'a', 'b.csv' => 'b', 'c.csv' => 'c']);

        $errors = $validator->validate($zipPath);

        $this->assertEquals(1, count($errors));
        $this->assertEquals('test.zip', $errors[0]['file']);
        $this->assertEquals(ZipArchiveValidator::ERROR_ARCHIVE_TOO_LARGE, $errors[0]['code']);
        $this->assertEquals('archive contains too many entries (3 > 2)', $errors[0]['message']);
    }

    /**
     * zip bomb protection : total uncompressed size.
     */
    public function testZipUncompressedSizeTooLarge()
    {
        $validator = new ZipArchiveValidator(new NullLogger(), maxUncompressedSize: 1000);
        $zipPath = $this->createZip(['a.csv' => str_repeat('a', 600), 'b.csv' => str_repeat('b', 600)]);

        $errors = $validator->validate($zipPath);

        $this->assertEquals(1, count($errors));
        $this->assertEquals(ZipArchiveValidator::ERROR_ARCHIVE_TOO_LARGE, $errors[0]['code']);
        $this->assertEquals('archive uncompressed size exceeds 1000 bytes', $errors[0]['message']);
    }

    /**
     * zip bomb protection : compression ratio.
     */
    public function testZipCompressionRatioTooHigh()
    {
        $validator = new ZipArchiveValidator(new NullLogger(), maxCompressionRatio: 100);
        $zipPath = $this->createZip(['bomb.csv' => str_repeat("\0", 2 * 1024 * 1024)]);

        $errors = $validator->validate($zipPath);

        $this->assertEquals(1, count($errors));
        $this->assertEquals(ZipArchiveValidator::ERROR_ARCHIVE_TOO_LARGE, $errors[0]['code']);
        $this->assertEquals("compression ratio of 'bomb.csv' exceeds 100", $errors[0]['message']);
    }
}
