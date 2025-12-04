<?php

namespace Tempest\Testing\Testers\Console;

use Tempest\Testing\Test;

final class ConsoleTesterTest
{
    use TestsConsole;

    #[Test]
    public function assertContains(): void
    {
        $this->console
            ->call('fixture')
            ->assertSuccess();
    }
}