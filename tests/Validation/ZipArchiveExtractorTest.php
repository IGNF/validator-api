<?php

namespace App\Tests\Validation;

use App\Exception\ZipArchiveValidationException;
use App\Tests\WebTestCase;
use App\Validation\FileContentValidator;
use App\Validation\ZipArchiveExtractor;
use App\Validation\ZipArchiveValidator;
use Psr\Log\NullLogger;

class ZipArchiveExtractorTest extends WebTestCase
{
    /**
     * @var ZipArchiveExtractor
     */
    private $extractor;

    public function setUp(): void
    {
        $this->extractor = new ZipArchiveExtractor(
            new ZipArchiveValidator(new NullLogger()),
            new FileContentValidator(),
            new NullLogger()
        );
    }

    /**
     * Valid archive : files are extracted, OS metadata entries are skipped.
     */
    public function testExtract()
    {
        $zipPath = $this->createZip([
            'data/' => '',
            'data/table.csv' => 'id;name',
            'data/sub/table.dbf' => "\x03\x7A",
            '__MACOSX/data/._table.csv' => "\x00\x05\x16\x07",
        ]);
        $targetPath = dirname($zipPath).'/extracted';

        $this->extractor->extract($zipPath, $targetPath);

        $this->assertStringEqualsFile($targetPath.'/data/table.csv', 'id;name');
        $this->assertStringEqualsFile($targetPath.'/data/sub/table.dbf', "\x03\x7A");
        $this->assertDirectoryDoesNotExist($targetPath.'/__MACOSX');
        $this->assertFalse(is_executable($targetPath.'/data/table.csv'));
    }

    /**
     * Sample dataset archive is extracted as with ZipArchive::extractTo.
     */
    public function testExtractSampleDataset()
    {
        $zipPath = $this->getTestDataDir().'/130010853_PM3_60_20180516.zip';
        $targetPath = $this->createTempDirectory('extract-');

        $this->extractor->extract($zipPath, $targetPath);

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if (!str_ends_with($name, '/')) {
                $this->assertStringEqualsFile($targetPath.'/'.$name, $zip->getFromIndex($i));
            }
        }
        $zip->close();
    }

    /**
     * Executables disguised with an allowed extension and fake files are rejected,
     * nothing is left in the target directory.
     */
    public function testExtractForbiddenContent()
    {
        $zipPath = $this->createZip([
            'data/table.csv' => 'id;name',
            'data/table.dbf' => "\x7FELF\x02\x01\x01".str_repeat("\0", 2000),
            'data/doc.pdf' => '<html><script>alert(1)</script></html>',
        ]);
        $targetPath = dirname($zipPath).'/extracted';

        try {
            $this->extractor->extract($zipPath, $targetPath);
            $this->fail('ZipArchiveValidationException expected');
        } catch (ZipArchiveValidationException $ex) {
            $this->assertEquals([
                [
                    'file' => 'data/table.dbf',
                    'code' => ZipArchiveValidator::ERROR_FILE_CONTENT_NOT_ALLOWED,
                    'message' => 'content is not allowed (ELF executable detected)',
                ],
                [
                    'file' => 'data/doc.pdf',
                    'code' => ZipArchiveValidator::ERROR_FILE_CONTENT_NOT_ALLOWED,
                    'message' => 'content does not match the .pdf extension',
                ],
            ], $ex->getErrors());
        }

        $this->assertDirectoryDoesNotExist($targetPath);
    }

    /**
     * Unsafe paths are never written, even without pre-validation.
     */
    public function testExtractPathTraversal()
    {
        $zipPath = $this->createZip(['data/../../evil.csv' => 'a']);
        $targetPath = dirname($zipPath).'/extracted';

        $this->expectExceptionMessage("Zip decompression failed (path is not allowed 'data/../../evil.csv')");
        try {
            $this->extractor->extract($zipPath, $targetPath);
        } finally {
            $this->assertFileDoesNotExist(dirname($zipPath, 2).'/evil.csv');
        }
    }
}
