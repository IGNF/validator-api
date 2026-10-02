<?php

namespace App\Tests\Validation;

use App\Validation\FileContentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileContentValidatorTest extends TestCase
{
    public static function validProvider(): array
    {
        return [
            'pdf' => ["%PDF-1.7\n", 'pdf'],
            'pdf with junk before signature' => ["\r\n%PDF-1.4", 'pdf'],
            'shp' => ["\x00\x00\x27\x0A\x00", 'shp'],
            'dbf' => ["\x03\x7A\x01\x01", 'dbf'],
            'gpkg' => ["SQLite format 3\x00...", 'gpkg'],
            'xml with BOM' => ["\xEF\xBB\xBF  <?xml version=\"1.0\"?>", 'xml'],
            'gml' => ['<gml:FeatureCollection>', 'gml'],
            'geojson' => ['{"type":"FeatureCollection"}', 'geojson'],
            'csv' => ['id;name', 'csv'],
            'png' => ["\x89PNG\r\n\x1A\n...", 'png'],
            'tif' => ["II*\x00...", 'tif'],
            'empty file' => ['', 'pdf'],
        ];
    }

    #[DataProvider('validProvider')]
    public function testValid(string $header, string $extension)
    {
        $this->assertNull((new FileContentValidator())->validateHeader($header, $extension));
    }

    public static function forbiddenProvider(): array
    {
        return [
            'ELF as dbf' => ["\x7FELF\x02\x01", 'dbf', 'ELF executable'],
            'PE as csv' => ["MZ\x90\x00", 'csv', 'Windows executable'],
            'Mach-O as dat' => ["\xCF\xFA\xED\xFE", 'dat', 'Mach-O executable'],
            'script as txt' => ["#!/bin/bash\nrm -rf /", 'txt', 'script'],
            'zip as pdf' => ["PK\x03\x04", 'pdf', 'zip archive'],
            'java class as id' => ["\xCA\xFE\xBA\xBE", 'id', 'Java class or Mach-O executable'],
        ];
    }

    #[DataProvider('forbiddenProvider')]
    public function testForbidden(string $header, string $extension, string $type)
    {
        $this->assertEquals(
            sprintf('content is not allowed (%s detected)', $type),
            (new FileContentValidator())->validateHeader($header, $extension)
        );
    }

    public static function signatureMismatchProvider(): array
    {
        return [
            'html as pdf' => ['<html>', 'pdf'],
            'text as shp' => ['not a shapefile', 'shp'],
            'text as dbf' => ['id;name', 'dbf'],
            'php as json' => ['<?php system($_GET["c"]);', 'json'],
            'text as gml' => ['hello', 'gml'],
            'pdf as png' => ['%PDF-1.7', 'png'],
        ];
    }

    #[DataProvider('signatureMismatchProvider')]
    public function testSignatureMismatch(string $header, string $extension)
    {
        $this->assertEquals(
            sprintf('content does not match the .%s extension', $extension),
            (new FileContentValidator())->validateHeader($header, $extension)
        );
    }
}
