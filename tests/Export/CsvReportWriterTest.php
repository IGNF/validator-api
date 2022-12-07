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

    public function setUp(): void {
        parent::setUp();
        $this->updateRegressTest = getenv('UPDATE_REGRESS_TEST') == '1';
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

	    if ( $this->updateRegressTest ){
            $writer->write($validation, $expectedPath);
        }

        $this->assertFileEquals(
            $targetPath,
            $expectedPath,
            implode(' ', [
                'Unexpected result for CsvReportWriterTest.',
                'Fix the problem or run test once with env UPDATE_REGRESS_TEST=1 if a change is expected'
            ])
        );
    }
}
