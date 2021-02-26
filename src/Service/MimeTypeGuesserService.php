<?php

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MimeTypeGuesserService
{

    /**
     * Checks and returns the file mime type with the linux command "file" if successful
     *
     * @param string $filepath
     * @return string
     * @throws ProcessFailedException
     */
    public function guessMimeType($filepath): string
    {
        $process = new Process(["file", "-b", "--mime-type", "-E", $filepath]);
        $process->setTimeout(100);
        $process->setIdleTimeout(100);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return preg_replace("/\r|\n/", "", $process->getOutput());
    }
}
