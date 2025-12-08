<?php

namespace Tempest\Testing\Testers\Console;

use Tempest\Testing\Test;
use function Tempest\Testing\test;

final class ConsoleTesterTest
{
    use TestsConsole;

    #[Test]
    public function assertSuccess(): void
    {
        test(fn () => $this->console->call('')->assertSuccess())->succeeds();
        test(fn () => $this->console->call('unknown')->assertSuccess())->fails('console exit code did not match expected `Tempest\\Console\\ExitCode::SUCCESS`, instead got `Tempest\\Console\\ExitCode::ERROR`');
    }

    #[Test]
    public function assertError(): void
    {
        test(fn () => $this->console->call('unknown')->assertError())->succeeds();
        test(fn () => $this->console->call('')->assertError())->fails('console exit code did not match expected `Tempest\\Console\\ExitCode::ERROR`, instead got `Tempest\\Console\\ExitCode::SUCCESS`');
    }
    
    #[Test]
    public function assertContains(): void
    {
        test(fn () => $this->console->call('')->assertContains('test'))->succeeds();
        test(fn () => $this->console->call('')->assertContains('unknown'))->fails('console output did not contain: `\'unknown\'`');
    }

    #[Test]
    public function assertDoesNotContain(): void
    {
        test(fn () => $this->console->call('')->assertDoesNotContain('unknown'))->succeeds();
        test(fn () => $this->console->call('')->assertDoesNotContain('test'))->fails('console output contained `\'test\'` while it shouldn\'t');
    }
}