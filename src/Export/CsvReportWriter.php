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
        'xsd_code' => 'xsdErrorCode',
        'xsd_msg' => 'xsdErrorMessage',
        'xsd_path' => 'xsdErrorPath',
    ];

    public function write(Validation $validation, $path = 'php://output')
    {
        $out = new SplFileObject($path, 'w');
        $out->setCsvControl(escape: '\\');
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
        foreach (self::MAPPING as $jsonName) {
            $value = $result[$jsonName] ?? null;
            // feat_bbox
            if (is_array($value)) {
                $row[] = implode(',', $value);
            } else {
                $row[] = $this->escapeFormula($value);
            }
        }

        return $row;
    }

    /**
     * Prevents CSV injection : values coming from the dataset (attribute values, identifiers...)
     * starting with "=", "+", "-", "@" are interpreted as formulas by spreadsheet applications.
     * Negative numbers are kept as is.
     */
    private function escapeFormula($value)
    {
        if (!is_string($value) || '' === $value || is_numeric($value)) {
            return $value;
        }
        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
