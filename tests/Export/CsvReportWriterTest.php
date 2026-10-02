<?php

namespace App\Tests\Storage;

use App\Entity\Validation;
use App\Export\CsvReportWriter;
use App\Tests\WebTestCase;

class CsvReportWriterTest extends WebTestCase
{
    /**
     * @var bool
     */
    private $updateRegressTest;

    public function setUp(): void
    {
        parent::setUp();
        $this->updateRegressTest = '1' == getenv('UPDATE_REGRESS_TEST');
    }

    /**
     * Test with a report generated with validator 3.3.x.
     *
     * @return void
     */
    public function testRegressValidator33()
    {
        $jsonPath = $this->getTestDataDir().'/validations/validation-3.3.json';
        $this->assertFileExists($jsonPath);
        $jsonData = json_decode(file_get_contents($jsonPath), true);
        $validation = new Validation();
        $validation->setResults($jsonData['results']);

        $expectedPath = $this->getTestDataDir().'/validations/validation-3.3-expected.csv';
        $targetPath = $this->createTempDirectory('export-').'/export-validator-33.csv';
        $writer = new CsvReportWriter();
        $writer->write($validation, $targetPath);

        if ($this->updateRegressTest) {
            $writer->write($validation, $expectedPath);
        }

        $this->assertFileEquals(
            $targetPath,
            $expectedPath,
            implode(' ', [
                'Unexpected result for CsvReportWriterTest.',
                'Fix the problem or run test once with env UPDATE_REGRESS_TEST=1 if a change is expected',
            ])
        );
    }

    /**
     * Test with a report generated with validator 4.4.x.
     *
     * @return void
     */
    public function testRegressValidator44()
    {
        $jsonPath = $this->getTestDataDir().'/validations/validation-4.4.json';
        $this->assertFileExists($jsonPath);
        $jsonData = json_decode(file_get_contents($jsonPath), true);
        $validation = new Validation();
        $validation->setResults($jsonData['results']);

        $expectedPath = $this->getTestDataDir().'/validations/validation-4.4-expected.csv';
        $targetPath = $this->createTempDirectory('export-').'/export-validator-44.csv';
        $writer = new CsvReportWriter();
        $writer->write($validation, $targetPath);

        if ($this->updateRegressTest) {
            $writer->write($validation, $expectedPath);
        }

        $this->assertFileEquals(
            $targetPath,
            $expectedPath,
            implode(' ', [
                'Unexpected result for CsvReportWriterTest.',
                'Fix the problem or run test once with env UPDATE_REGRESS_TEST=1 if a change is expected',
            ])
        );
    }

    /**
     * Values from the dataset can't be interpreted as formulas by spreadsheet applications.
     */
    public function testFormulaInjection()
    {
        $validation = new Validation();
        $validation->setResults([
            ['code' => 'ATTRIBUTE_INVALID', 'message' => '=HYPERLINK("http://evil.example","x")', 'id' => '-12.5', 'attribute' => '@SUM(A1)', 'featureId' => '+33'],
        ]);
        $targetPath = $this->createTempDirectory('export-').'/export.csv';

        (new CsvReportWriter())->write($validation, $targetPath);

        $rows = array_map(fn ($line) => str_getcsv($line, escape: '\\'), file($targetPath, FILE_IGNORE_NEW_LINES));
        $row = array_combine($rows[0], $rows[1]);
        $this->assertEquals('\'=HYPERLINK("http://evil.example","x")', $row['message']);
        $this->assertEquals('\'@SUM(A1)', $row['attribute']);
        // numbers are kept as is
        $this->assertEquals('-12.5', $row['id']);
        $this->assertEquals('+33', $row['feat_id']);
    }
}
