<?php

namespace App\Export;

use App\Entity\Validation;
use SplFileObject;

/**
 * Converts results from a Validation from JSON to CSV.
 */
class CsvReportWriter
{
    /**
     * CSV_COLUMN -> JSON_PROPERTY mapping. Note that naming and behavior is
     * taken from www.geoportail-urbanisme.gouv.fr :
     * - "scope" is not exported to CSV
     * - Naming WKT the errorGeometry simplifies reading with GDAL/ogr2ogr and QuantumGIS.
     */
    public const MAPPING = [
        'code' => 'code',
        'level' => 'level',
        'message' => 'message',
        'standard' => 'documentModel',
        'fileModel' => 'fileModel',
        'attribute' => 'attribute',
        'file' => 'file',
        'id' => 'id',
        'feat_bbox' => 'featureBbox',
        'WKT' => 'errorGeometry',
        'feat_id' => 'featureId',
    ];

    public function write(Validation $validation, $path = 'php://output')
    {
        $out = new SplFileObject($path, 'w');
        $out->fputcsv($this->getHeader());

        foreach ($validation->getResults() as $result) {
            $out->fputcsv($this->toCsvRow($result));
        }
    }

    /**
     * Get CSV header according to MAPPING.
     *
     * @return array
     */
    private function getHeader()
    {
        return array_keys(self::MAPPING);
    }

    /**
     * Convert ValidatorError in JSON format to a CSV row.
     *
     * @return array
     */
    private function toCsvRow(array $result)
    {
        $row = [];
        foreach (self::MAPPING as $csvName => $jsonName) {
            $value = @$result[$jsonName];
            // feat_bbox
            if ( is_array($value) ){
                $row[] = implode(',',$value);
            }else{
                $row[] = $value;
            }
        }

        return $row;
    }
}
