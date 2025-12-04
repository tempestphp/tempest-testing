<?php

namespace Tempest\Testing\Tests\Fixtures;

use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;

final class FixtureCommand
{
    use HasConsole;

    #[ConsoleCommand]
    public function __invoke(): void
    {
        $this->success('Done');
    }
}