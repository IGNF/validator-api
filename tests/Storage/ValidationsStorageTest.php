<?php

namespace App\Tests\Storage;

use App\Tests\WebTestCase;

class ValidationsStorageTest extends WebTestCase
{
    /**
     * Ensure that path is suffixed for test validations.
     *
     * @return void
     */
    public function testPathIsSuffixed()
    {
        $storage = $this->getValidationsStorage();
        $this->assertStringEndsWith('var/data/', $storage->getPath());
    }
}
