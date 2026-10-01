<?php

namespace App\Validation;

use App\Exception\ValidationProcessException;
use App\Exception\ZipArchiveValidationException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

/**
 * Safe extraction of a dataset archive previously checked by ZipArchiveValidator.
 *
 * Instead of ZipArchive::extractTo, each entry is streamed to disk so that :
 *  - the content of each file is checked (no executable, signature matching the extension),
 *  - the uncompressed size declared in the central directory is enforced (zip bomb protection),
 *  - files are created as regular non executable files, whatever the attributes in the archive.
 *
 * Inspired by gpu-site Ign\Gpu\Zip\ZipExtractor.
 */
class ZipArchiveExtractor
{
    private const CHUNK_SIZE = 65536;

    public function __construct(
        private ZipArchiveValidator $zipArchiveValidator,
        private FileContentValidator $fileContentValidator,
        private LoggerInterface $logger,
    ) {}

    /**
     * Extracts the archive zipPath in targetPath.
     *
     * @throws ZipArchiveValidationException if a file is not allowed (targetPath is then removed)
     * @throws ValidationProcessException     if the archive can't be read or extracted
     */
    public function extract(string $zipPath, string $targetPath): void
    {
        $zip = new ZipArchive();
        if (true !== $zip->open($zipPath)) {
            throw new ValidationProcessException('Zip decompression failed');
        }

        $errors = [];
        try {
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if ($this->zipArchiveValidator->isIgnoredEntry($name)) {
                    continue;
                }
                // should have been rejected by ZipArchiveValidator
                if (!$this->zipArchiveValidator->isSafePath($name)) {
                    throw new ValidationProcessException(sprintf("Zip decompression failed (path is not allowed '%s')", $name));
                }

                $path = $targetPath . '/' . $name;
                if (str_ends_with($name, '/')) {
                    $this->createDirectory($path);
                    continue;
                }

                $error = $this->extractEntry($zip, $i, $stat, $path);
                if ($error) {
                    $this->logger->error(sprintf('[ZipArchiveExtractor] %s : %s', $name, $error));
                    $errors[] = [
                        'file' => $name,
                        'code' => ZipArchiveValidator::ERROR_FILE_CONTENT_NOT_ALLOWED,
                        'message' => $error,
                    ];
                }
            }
        } catch (Exception $ex) {
            (new Filesystem())->remove($targetPath);
            throw $ex;
        } finally {
            $zip->close();
        }

        if (!empty($errors)) {
            (new Filesystem())->remove($targetPath);
            throw new ZipArchiveValidationException($errors);
        }
    }

    /**
     * Streams an entry to path, checking its content and its size.
     *
     * @return string|null error message if the file is not allowed (the file is then removed)
     */
    private function extractEntry(ZipArchive $zip, int $index, array $stat, string $path): ?string
    {
        $extension = strtolower(pathinfo($stat['name'], PATHINFO_EXTENSION));

        $input = $zip->getStreamIndex($index);
        if (false === $input) {
            throw new ValidationProcessException(sprintf("Zip decompression failed (can't read '%s')", $stat['name']));
        }

        $this->createDirectory(dirname($path));
        $output = fopen($path, 'wb');
        if (false === $output) {
            fclose($input);
            throw new ValidationProcessException(sprintf("Zip decompression failed (can't write '%s')", $stat['name']));
        }

        $error = null;
        $written = 0;
        try {
            $header = $this->readHeader($input);
            $error = $this->fileContentValidator->validateHeader($header, $extension);

            $chunk = $header;
            while (null === $error && '' !== $chunk) {
                $written += strlen($chunk);
                // the actual size must not exceed the size checked by ZipArchiveValidator
                if ($written > $stat['size']) {
                    $error = sprintf('uncompressed size exceeds the declared size (%d bytes)', $stat['size']);
                    break;
                }
                fwrite($output, $chunk);
                $chunk = (string) fread($input, self::CHUNK_SIZE);
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        if ($error) {
            unlink($path);
        }

        return $error;
    }

    /**
     * Reads the first bytes of a stream (fread on a zip stream may return less than requested).
     *
     * @param resource $input
     */
    private function readHeader($input): string
    {
        $header = '';
        while (strlen($header) < FileContentValidator::HEADER_LENGTH && !feof($input)) {
            $chunk = fread($input, FileContentValidator::HEADER_LENGTH - strlen($header));
            if (false === $chunk || '' === $chunk) {
                break;
            }
            $header .= $chunk;
        }

        return $header;
    }

    private function createDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0o775, true) && !is_dir($path)) {
            throw new ValidationProcessException("Zip decompression failed (can't create directory)");
        }
    }
}
