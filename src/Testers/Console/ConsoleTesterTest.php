<?php

namespace Tempest\Testing\Testers\Console;

use Tempest\Console\ExitCode;
use Tempest\Testing\Test;
use function Tempest\Testing\test;

final class ConsoleTesterTest
{
    use TestsConsole;

    #[Test]
    public function succeeds(): void
    {
        test(fn () => $this->console->call('')->succeeds())->succeeds();
        test(fn () => $this->console->call('unknown')->succeeds())->fails('console exit code did not match expected `Tempest\\Console\\ExitCode::SUCCESS`, instead got `Tempest\\Console\\ExitCode::ERROR`');
    }

    #[Test]
    public function hasExitCode(): void
    {
        test(fn () => $this->console->call('')->hasExitCode(ExitCode::SUCCESS))->succeeds();
        test(fn () => $this->console->call('unknown')->hasExitCode(ExitCode::SUCCESS))->fails('console exit code did not match expected `Tempest\\Console\\ExitCode::SUCCESS`, instead got `Tempest\\Console\\ExitCode::ERROR`');
    }

    #[Test]
    public function fails(): void
    {
        test(fn () => $this->console->call('unknown')->fails())->succeeds();
        test(fn () => $this->console->call('')->fails())->fails('console exit code did not match expected `Tempest\\Console\\ExitCode::ERROR`, instead got `Tempest\\Console\\ExitCode::SUCCESS`');
    }

    #[Test]
    public function contains(): void
    {
        test(fn () => $this->console->call('')->contains('test'))->succeeds();
        test(fn () => $this->console->call('')->contains('unknown'))->fails('console output did not contain: `\'unknown\'`');
    }

    #[Test]
    public function containsNot(): void
    {
        test(fn () => $this->console->call('')->containsNot('unknown'))->succeeds();
        test(fn () => $this->console->call('')->containsNot('test'))->fails('console output contained `\'test\'` while it shouldn\'t');
    }

    #[Test]
    public function isJson(): void
    {
        test(fn () => $this->console->call('config:show')->isJson())->succeeds();
    }
}