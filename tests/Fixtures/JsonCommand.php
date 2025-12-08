<?php

namespace Tempest\Testing\Tests\Fixtures;

use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;

final class JsonCommand
{
    use HasConsole;

    #[ConsoleCommand]
    public function __invoke(): void
    {
        $this->writeln(json_encode(['foo' => 'bar']));
    }
}