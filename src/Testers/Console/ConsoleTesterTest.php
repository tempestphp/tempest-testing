<?php

namespace Tempest\Testing\Testers\Console;

use Tempest\Testing\Test;

final class ConsoleTesterTest
{
    use TestsConsole;

    #[Test]
    public function assertFail(): void
    {
        $this->console
            ->call('fixture')
            ->assertError();
    }
}