<?php

namespace App\Service;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Helper retrieving mine type using "file" command.
 *
 * @author Orka Arnest CRUZE
 */
class MimeTypeGuesserService
{
    /**
     * Checks and returns the file mime type with the linux command "file" if successful.
     *
     * @param string $filepath
     *
     * @throws ProcessFailedException
     * @throws FileNotFoundException
     */
    public function guessMimeType($filepath): string
    {
        if (!file_exists($filepath)) {
            throw new FileNotFoundException(sprintf("file '%s' not found'", $filepath));
        }

        $process = new Process(['file', '-b', '--mime-type', '-E', $filepath]);
        $process->setTimeout(100);
        $process->setIdleTimeout(100);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return preg_replace("/\r|\n/", '', $process->getOutput());
    }
}
