<?php

namespace App\Validation;

use App\Exception\ZipArchiveValidationException;
use Psr\Log\LoggerInterface;
use ZipArchive;

class ZipArchiveValidator
{

    const REGEXP_VALID_FILENAME = "/^[A-Za-z0-9-_.\/]+$/";
    const ERROR_BAD_ARCHIVE = "BAD_ARCHIVE";
    const ERROR_BAD_ARCHIVE_FILENAME = "BAD_ARCHIVE_FILENAME";

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Validates a ZIP returning a set of errors in the same format as the validator
     *
     * @param string $zipPath
     * @return void
     * @throws ZipArchiveValidationException
     */
    public function validate($zipPath)
    {
        $errors = [];
        $files = [];

        try {
            $files = $this->listFiles($zipPath);
        } catch (\Exception $ex) {
            $errors[] = [
                'file' => pathinfo($zipPath, PATHINFO_BASENAME),
                'message' => $ex->getMessage(),
                'code' => self::ERROR_BAD_ARCHIVE,
            ];
        }

        foreach ($files as $filepath) {
            if ($filenameErrors = $this->validateFilename($filepath)) {
                $errors[] = $filenameErrors;
            }
        }

        if (count($errors) > 0) {
            throw new ZipArchiveValidationException($errors);
        }
    }

    /**
     * Returns the list of names of the files in the archive
     *
     * @param string $zipPath
     * @return array
     */
    private function listFiles($zipPath)
    {
        $zipName = pathinfo($zipPath, PATHINFO_BASENAME);
        if (!file_exists($zipPath)) {
            throw new \Exception(sprintf("The zip archive file %s doesn't exist", $zipName));
        }

        $zipArchive = new ZipArchive();
        if ($zipArchive->open($zipPath) !== true) {
            $this->logger->error(sprintf("[ZipArchiveValidator] Impossible to open archive %s", $zipPath));
            throw new \Exception(sprintf("Impossible to open archive %s", $zipName));
        }

        $files = [];
        for ($i = 0; $i < $zipArchive->numFiles; ++$i) {
            $stat = $zipArchive->statIndex($i);
            $files[] = $stat['name'];
        }

        if (empty($files)) {
            $this->logger->error(sprintf('[ZipArchiveValidator] Archive %s is empty', $zipPath));
            throw new \Exception(sprintf("Archive %s is empty", $zipName));
        }

        $zipArchive->close();
        return $files;
    }

    /**
     * Validates the filename of the provided filepath
     *
     * Validation criteria:
     *  - must be UTF-8
     *  - must match the regular expression self::REGEXP_VALID_FILENAME
     *
     * @param string $filepath
     * @return array|null
     */
    private function validateFilename($filepath)
    {
        $filename = pathinfo($filepath, PATHINFO_BASENAME);
        $error = false;
        $message = sprintf(
            "filename %s is valid",
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

        $this->logger->debug(sprintf("[ZipArchiveValidator] %s", $message));

        return $error ? [
            'file' => $filepath,
            'code' => self::ERROR_BAD_ARCHIVE_FILENAME,
            'message' => $message,
        ] : null;
    }

}
