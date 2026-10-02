<?php

namespace App\Tests\Storage;

use App\Storage\ValidationsStorage;
use App\Tests\WebTestCase;
use League\Flysystem\FilesystemOperator;

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
        $this->assertStringEndsWith('data/validations-test', $storage->getPath());
    }

    /**
     * STORAGE_TYPE selects the flysystem storage (it was read with getenv(), ignoring the .env files).
     */
    public function testStorageType()
    {
        $s3 = $this->createStub(FilesystemOperator::class);
        $local = $this->createStub(FilesystemOperator::class);

        $this->assertSame($s3, (new ValidationsStorage('/tmp', $s3, $local, 'S3'))->getStorage());
        $this->assertSame($local, (new ValidationsStorage('/tmp', $s3, $local, 'local'))->getStorage());
        $this->assertSame($local, (new ValidationsStorage('/tmp', $s3, $local))->getStorage());
    }
}
