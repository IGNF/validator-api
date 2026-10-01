<?php

namespace App\Validation;

use Exception;
use Psr\Log\LoggerInterface;
use ZipArchive;

/**
 * Component class for the pre-validation of a zip archive before its extraction.
 *
 * Only the central directory is read (no decompression):
 *  - zip bomb protection (number of entries, total uncompressed size, compression ratio),
 *  - entries must be safe to extract (no absolute path, no "..", no symlink, no encryption),
 *  - file names must be valid and extensions must be allowed.
 *
 * Note that the declared sizes are enforced at extraction time by ZipArchiveExtractor.
 */
class ZipArchiveValidator
{
    public const REGEXP_VALID_FILENAME = "/^[A-Za-z0-9-_.\/]+$/";
    public const ERROR_BAD_ARCHIVE = 'BAD_ARCHIVE';
    public const ERROR_BAD_ARCHIVE_FILENAME = 'BAD_ARCHIVE_FILENAME';
    public const ERROR_ARCHIVE_TOO_LARGE = 'ARCHIVE_TOO_LARGE';
    public const ERROR_BAD_ARCHIVE_ENTRY = 'BAD_ARCHIVE_ENTRY';
    public const ERROR_FILE_EXTENSION_NOT_ALLOWED = 'FILE_EXTENSION_NOT_ALLOWED';
    public const ERROR_FILE_CONTENT_NOT_ALLOWED = 'FILE_CONTENT_NOT_ALLOWED';

    public const DEFAULT_MAX_ENTRIES = 10000;
    public const DEFAULT_MAX_UNCOMPRESSED_SIZE = 5 * 1024 * 1024 * 1024;
    public const DEFAULT_MAX_COMPRESSION_RATIO = 1000;

    /**
     * The compression ratio is only checked above this uncompressed size.
     */
    private const COMPRESSION_RATIO_MIN_SIZE = 1024 * 1024;

    /**
     * Allowed extensions (lower case) : formats read by validator-cli.jar and their sidecar files.
     */
    public const ALLOWED_EXTENSIONS = [
        // tables and documents read by validator-cli.jar
        'dbf', 'tab', 'gml', 'csv', 'gpkg', 'xml', 'pdf',
        // shapefile sidecars
        'shp', 'shx', 'prj', 'cpg', 'qpj', 'sbn', 'sbx', 'qix', 'fix', 'ain', 'aih', 'atx', 'ixs', 'mxs', 'fbn', 'fbx',
        // MapInfo
        'map', 'id', 'dat', 'ind', 'mif', 'mid',
        // other geographic and metadata formats
        'geojson', 'json', 'kml', 'gpx', 'dxf', 'xsd', 'qmd', 'vrows',
        // images and text
        'png', 'jpg', 'jpeg', 'tif', 'tiff', 'txt',
    ];

    /**
     * Entries added by operating systems, ignored (neither validated nor extracted).
     */
    private const REGEXP_IGNORED_ENTRY = '/(^|\/)(__MACOSX\/|\.DS_Store$|Thumbs\.db$|desktop\.ini$)/i';

    private const UNIX_FILE_TYPE_MASK = 0o170000;
    private const UNIX_SYMLINK = 0o120000;

    public function __construct(
        private LoggerInterface $logger,
        private int $maxEntries = self::DEFAULT_MAX_ENTRIES,
        private int $maxUncompressedSize = self::DEFAULT_MAX_UNCOMPRESSED_SIZE,
        private int $maxCompressionRatio = self::DEFAULT_MAX_COMPRESSION_RATIO,
    ) {}

    /**
     * Validates a ZIP returning a set of errors in the same format as the validator.
     *
     * @param string $zipPath
     *
     * @return array
     */
    public function validate($zipPath)
    {
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);

        try {
            $entries = $this->listEntries($zipPath);
        } catch (Exception $ex) {
            return [[
                'file' => $zipName,
                'message' => $ex->getMessage(),
                'code' => self::ERROR_BAD_ARCHIVE,
            ]];
        }

        $sizeError = $this->validateSizes($zipName, $entries);
        if ($sizeError) {
            return [$sizeError];
        }

        $errors = [];
        foreach ($entries as $entry) {
            if ($this->isIgnoredEntry($entry['name'])) {
                continue;
            }
            $entryError = $this->validateEntry($entry);
            if ($entryError) {
                $errors[] = $entryError;
            }
        }

        return $errors;
    }

    /**
     * True for entries added by operating systems (__MACOSX/, .DS_Store...).
     */
    public function isIgnoredEntry(string $name): bool
    {
        return 1 === preg_match(self::REGEXP_IGNORED_ENTRY, $name);
    }

    /**
     * True if the path is relative and stays inside the extraction directory.
     */
    public function isSafePath(string $name): bool
    {
        if ('' === $name || str_contains($name, "\0") || str_contains($name, '\\')) {
            return false;
        }
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            return false;
        }

        return !in_array('..', explode('/', $name), true);
    }

    /**
     * Returns the stats of the entries of the archive, with their unix file type.
     *
     * @param string $zipPath
     *
     * @return array
     */
    private function listEntries($zipPath)
    {
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);
        if (!file_exists($zipPath)) {
            throw new Exception(sprintf("The zip archive file %s doesn't exist", $zipName));
        }

        $zipArchive = new ZipArchive();
        if (true !== $zipArchive->open($zipPath)) {
            $this->logger->error(sprintf('[ZipArchiveValidator] Impossible to open archive %s', $zipPath));
            throw new Exception(sprintf('Impossible to open archive %s', $zipName));
        }

        $entries = [];
        for ($i = 0; $i < $zipArchive->numFiles; ++$i) {
            $entry = $zipArchive->statIndex($i);
            $entry['symlink'] = false;
            $opsys = 0;
            $attr = 0;
            if ($zipArchive->getExternalAttributesIndex($i, $opsys, $attr) && ZipArchive::OPSYS_UNIX === $opsys) {
                $entry['symlink'] = self::UNIX_SYMLINK === (($attr >> 16) & self::UNIX_FILE_TYPE_MASK);
            }
            $entries[] = $entry;
        }

        $zipArchive->close();

        if (empty($entries)) {
            $this->logger->error(sprintf('[ZipArchiveValidator] Archive %s is empty', $zipPath));
            throw new Exception(sprintf('Archive %s is empty', $zipName));
        }

        return $entries;
    }

    /**
     * Zip bomb protection based on the sizes declared in the central directory.
     *
     * @return array|null
     */
    private function validateSizes(string $zipName, array $entries)
    {
        $message = null;
        $totalSize = 0;

        if (count($entries) > $this->maxEntries) {
            $message = sprintf('archive contains too many entries (%d > %d)', count($entries), $this->maxEntries);
        }

        foreach ($entries as $entry) {
            if ($message) {
                break;
            }
            $totalSize += $entry['size'];
            if ($totalSize > $this->maxUncompressedSize) {
                $message = sprintf('archive uncompressed size exceeds %d bytes', $this->maxUncompressedSize);
            } elseif ($entry['size'] > self::COMPRESSION_RATIO_MIN_SIZE
                && $entry['size'] > $this->maxCompressionRatio * max($entry['comp_size'], 1)
            ) {
                $message = sprintf("compression ratio of '%s' exceeds %d", $entry['name'], $this->maxCompressionRatio);
            }
        }

        if (!$message) {
            return null;
        }

        $this->logger->error(sprintf('[ZipArchiveValidator] %s : %s', $zipName, $message));

        return [
            'file' => $zipName,
            'code' => self::ERROR_ARCHIVE_TOO_LARGE,
            'message' => $message,
        ];
    }

    /**
     * Validates an entry of the archive.
     *
     * @return array|null
     */
    private function validateEntry(array $entry)
    {
        $filepath = $entry['name'];

        if (!$this->isSafePath($filepath)) {
            return $this->entryError($filepath, self::ERROR_BAD_ARCHIVE_ENTRY, sprintf("path is not allowed ('%s')", $filepath));
        }
        if ($entry['symlink']) {
            return $this->entryError($filepath, self::ERROR_BAD_ARCHIVE_ENTRY, sprintf("symbolic links are not allowed ('%s')", $filepath));
        }
        if (ZipArchive::EM_NONE !== $entry['encryption_method']) {
            return $this->entryError($filepath, self::ERROR_BAD_ARCHIVE_ENTRY, sprintf("encrypted files are not allowed ('%s')", $filepath));
        }

        $filenameError = $this->validateFilename($filepath);
        if ($filenameError) {
            return $filenameError;
        }

        // directories
        if (str_ends_with($filepath, '/')) {
            return null;
        }

        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->entryError($filepath, self::ERROR_FILE_EXTENSION_NOT_ALLOWED, sprintf(
                "file extension is not allowed ('%s')",
                pathinfo($filepath, PATHINFO_BASENAME)
            ));
        }

        return null;
    }

    private function entryError(string $filepath, string $code, string $message): array
    {
        $this->logger->debug(sprintf('[ZipArchiveValidator] %s', $message));

        return [
            'file' => $filepath,
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * Validates the filename of the provided filepath.
     *
     * Validation criteria:
     *  - must be UTF-8
     *  - must match the regular expression self::REGEXP_VALID_FILENAME
     *
     * @param string $filepath
     *
     * @return array|null
     */
    private function validateFilename($filepath)
    {
        $filename = pathinfo($filepath, PATHINFO_BASENAME);
        $error = false;
        $message = sprintf(
            'filename %s is valid',
            $filename
        );

        if (false === mb_detect_encoding($filename, 'UTF-8', true)) {
            $error = true;
            $message = sprintf(
                "filename is non UTF-8 ('%s')",
                $filename
            );
        }

        if (!preg_match(self::REGEXP_VALID_FILENAME, $filename)) {
            $error = true;
            $message = sprintf(
                "filename is not valid ('%s' must match %s)",
                $filename,
                self::REGEXP_VALID_FILENAME
            );
        }

        $this->logger->debug(sprintf('[ZipArchiveValidator] %s', $message));

        return $error ? [
            'file' => $filepath,
            'code' => self::ERROR_BAD_ARCHIVE_FILENAME,
            'message' => $message,
        ] : null;
    }
}
