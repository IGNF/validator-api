<?php

namespace App\Validation;

/**
 * Checks the first bytes of a file extracted from a dataset archive:
 *  - rejects executables and nested archives whatever their extension,
 *  - checks the signature (magic bytes) of the formats that have a reliable one.
 *
 * Inspired by gpu-site Ign\Gpu\Zip\FileValidator (MIME detection is not used as it
 * is unreliable for geographic formats).
 */
class FileContentValidator
{
    /**
     * Number of bytes to read at the beginning of a file to check its content.
     */
    public const HEADER_LENGTH = 1024;

    /**
     * Signatures of executables, scripts and archives (rejected whatever the extension).
     */
    private const FORBIDDEN_SIGNATURES = [
        "\x7FELF" => 'ELF executable',
        'MZ' => 'Windows executable',
        "\xCA\xFE\xBA\xBE" => 'Java class or Mach-O executable',
        "\xFE\xED\xFA\xCE" => 'Mach-O executable',
        "\xFE\xED\xFA\xCF" => 'Mach-O executable',
        "\xCE\xFA\xED\xFE" => 'Mach-O executable',
        "\xCF\xFA\xED\xFE" => 'Mach-O executable',
        "\x00asm" => 'WebAssembly module',
        '#!' => 'script',
        "PK\x03\x04" => 'zip archive',
        "PK\x05\x06" => 'zip archive',
        "Rar!\x1A\x07" => 'rar archive',
        "7z\xBC\xAF\x27\x1C" => '7z archive',
        "\x1F\x8B" => 'gzip archive',
    ];

    /**
     * Expected signatures by extension (no entry : no reliable signature).
     */
    private const MAGIC_BYTES = [
        'pdf' => ['%PDF'],
        'png' => ["\x89PNG\r\n\x1A\n"],
        'jpg' => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'tif' => ["II*\x00", "MM\x00*"],
        'tiff' => ["II*\x00", "MM\x00*"],
        'shp' => ["\x00\x00\x27\x0A"],
        'shx' => ["\x00\x00\x27\x0A"],
        'gpkg' => ["SQLite format 3\x00"],
    ];

    /**
     * Text formats which must start with a given character (after BOM and whitespaces).
     */
    private const TEXT_FIRST_CHARS = [
        'xml' => ['<'],
        'gml' => ['<'],
        'kml' => ['<'],
        'gpx' => ['<'],
        'xsd' => ['<'],
        'qmd' => ['<'],
        'json' => ['{', '['],
        'geojson' => ['{', '['],
    ];

    /**
     * Known dBASE version bytes.
     */
    private const DBF_VERSIONS = [
        0x02, 0x03, 0x04, 0x05, 0x30, 0x31, 0x32, 0x43, 0x63, 0x7B,
        0x83, 0x8B, 0x8C, 0xCB, 0xE5, 0xF5, 0xFB,
    ];

    /**
     * Validates the header of a file.
     *
     * @param string $header    first bytes of the file (up to self::HEADER_LENGTH)
     * @param string $extension lower case extension of the file
     *
     * @return string|null error message if the content is not allowed
     */
    public function validateHeader(string $header, string $extension): ?string
    {
        $forbiddenType = $this->findForbiddenType($header);
        if (null !== $forbiddenType) {
            return sprintf('content is not allowed (%s detected)', $forbiddenType);
        }

        // empty files are harmless
        if ('' === $header || $this->matchesExtension($header, $extension)) {
            return null;
        }

        return sprintf('content does not match the .%s extension', $extension);
    }

    /**
     * Returns the type of executable or archive detected in the header, if any.
     */
    private function findForbiddenType(string $header): ?string
    {
        foreach (self::FORBIDDEN_SIGNATURES as $signature => $type) {
            if (str_starts_with($header, $signature)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * True if the (non empty) header is consistent with the extension.
     */
    private function matchesExtension(string $header, string $extension): bool
    {
        if ('pdf' === $extension) {
            // PDF readers accept junk before the signature within the first 1024 bytes
            return str_contains($header, '%PDF');
        }

        if ('dbf' === $extension) {
            return in_array(ord($header[0]), self::DBF_VERSIONS, true);
        }

        if (isset(self::MAGIC_BYTES[$extension])) {
            foreach (self::MAGIC_BYTES[$extension] as $signature) {
                if (str_starts_with($header, $signature)) {
                    return true;
                }
            }

            return false;
        }

        if (isset(self::TEXT_FIRST_CHARS[$extension])) {
            $text = $this->normalizeTextHeader($header);

            return '' === $text || in_array($text[0], self::TEXT_FIRST_CHARS[$extension], true);
        }

        // no reliable signature for this extension
        return true;
    }

    /**
     * Removes UTF-8 BOM and leading whitespaces.
     */
    private function normalizeTextHeader(string $header): string
    {
        if (str_starts_with($header, "\xEF\xBB\xBF")) {
            $header = substr($header, 3);
        }

        return ltrim($header, " \t\r\n\x0B\x0C");
    }
}
