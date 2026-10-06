<?php

namespace App\Tests\Validation;

use App\Entity\Validation;
use App\Storage\ValidationsStorage;
use App\Validation\ValidatorCLI;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ValidatorCLITest extends TestCase
{
    /**
     * Arguments of validator-cli.jar built from the arguments of the validation.
     */
    private function reconstructArgs(array $arguments): array
    {
        $validatorCli = new ValidatorCLI(
            $this->createStub(ValidationsStorage::class),
            __FILE__,
            '',
            '',
            new NullLogger()
        );
        $validation = new Validation();
        $validation->setArguments($arguments);

        return (new \ReflectionMethod($validatorCli, 'reconstructArgs'))->invoke($validatorCli, $validation);
    }

    public function testDgprSkipControlsAsFlags(): void
    {
        $args = $this->reconstructArgs([
            'plugins' => 'DGPR',
            'dgpr-skip-inclusion' => true,
            'dgpr-skip-graph-topology' => false,
        ]);

        $this->assertSame(['--plugins', 'DGPR', '--dgpr-skip-inclusion'], $args);
    }
}
