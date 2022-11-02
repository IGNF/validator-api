<?php

namespace App\Tests\Service;

use App\Service\MimeTypeGuesserService;
use App\Tests\WebTestCase;
use Exception;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class MimeTypeGuesserServiceTest extends WebTestCase
{
    /**
     * @var MimeTypeGuesserService
     */
    private $mimeTypeGuesser;

    public function setUp(): void
    {
        $this->mimeTypeGuesser = new MimeTypeGuesserService();
    }

    /**
     * Test MimeTypeGuesser on this file.
     *
     * @return void
     */
    public function testPhpFile()
    {
        $this->assertEquals(
            'text/x-php',
            $this->mimeTypeGuesser->guessMimeType(__FILE__)
        );
    }

    /**
     * Test MimeTypeGuesser on file not found.
     *
     * @return void
     */
    public function testFileNotFound()
    {
        $thrown = false;
        try {
            $this->mimeTypeGuesser->guessMimeType(__DIR__.'/not-found.txt');
        } catch (Exception $e) {
            $this->assertInstanceOf(FileNotFoundException::class, $e);
            $this->assertStringContainsString("not-found.txt' not found", $e->getMessage());
            $thrown = true;
        }
        $this->assertTrue($thrown, 'an exception is expected if file is not found');
    }
}
