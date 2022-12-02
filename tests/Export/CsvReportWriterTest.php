<?php

namespace App\Tests\Storage;

use App\Entity\Validation;
use App\Export\CsvReportWriter;
use App\Tests\WebTestCase;

class CsvReportWriterTest extends WebTestCase
{
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

	// KEEP IT COMMENTED (except if regress test is updated)
        //$writer->write($validation, $expectedPath);

        $this->assertFileEquals($targetPath, $expectedPath);
    }
}
