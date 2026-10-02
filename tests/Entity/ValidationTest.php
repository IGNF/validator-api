<?php

namespace App\Tests\Entity;

use App\Entity\Validation;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testSetDatasetName()
    {
        $validation = (new Validation())->setDatasetName('130010853_PM3_60_20180516.v2');

        $this->assertEquals('130010853_PM3_60_20180516.v2', $validation->getDatasetName());
    }

    /**
     * Names that could escape the validation directory or be parsed as command options are rejected.
     */
    public function testSetDatasetNameRejectsUnsafeNames()
    {
        foreach (['', '.', '..', '../other', 'a/b', '-r', 'a b', str_repeat('a', 101)] as $name) {
            try {
                (new Validation())->setDatasetName($name);
                $this->fail(sprintf("'%s' should be rejected", $name));
            } catch (\InvalidArgumentException $ex) {
                $this->assertEquals(sprintf("Invalid dataset name '%s'", $name), $ex->getMessage());
            }
        }
    }
}
