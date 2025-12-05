<?php

namespace Tempest\Testing\Testers\Console;

use Tempest\Testing\Test;

final class ConsoleTesterTest
{
    use TestsConsole;

    #[Test]
    public function assertSuccess(): void
    {
        $this->console
            ->call('')
            ->assertSuccess();
    }

    #[Test]
    public function assertError(): void
    {
        $this->console
            ->call('unknown')
            ->assertError();
    }
}