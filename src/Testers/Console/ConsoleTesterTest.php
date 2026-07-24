<?php

namespace Tempest\Testing\Testers\Console;

use Tempest\Console\Console;
use Tempest\Console\ExitCode;
use Tempest\Container\Container;
use Tempest\Testing\Test;

use function Tempest\Testing\test;

final class ConsoleTesterTest
{
    use TestsConsole;

    #[Test]
    public function succeeds(): void
    {
        test(fn () => $this->console->call('')->succeeds())->succeeds();
        test(fn () => $this->console->call('unknown')->succeeds())
            ->fails('console exit code did not match expected `Tempest\\Console\\ExitCode::SUCCESS`, instead got `Tempest\\Console\\ExitCode::ERROR`');
    }

    #[Test]
    public function hasExitCode(): void
    {
        test(fn () => $this->console->call('')->hasExitCode(ExitCode::SUCCESS))->succeeds();
        test(fn () => $this->console->call('unknown')->hasExitCode(ExitCode::SUCCESS))
            ->fails('console exit code did not match expected `Tempest\\Console\\ExitCode::SUCCESS`, instead got `Tempest\\Console\\ExitCode::ERROR`');
    }

    #[Test]
    public function fails(): void
    {
        test(fn () => $this->console->call('unknown')->fails())->succeeds();
        test(fn () => $this->console->call('')->fails())
            ->fails('console exit code did not match expected `Tempest\\Console\\ExitCode::ERROR`, instead got `Tempest\\Console\\ExitCode::SUCCESS`');
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

    #[Test]
    public function restores_the_original_console_after_each_test(Container $container): void
    {
        $originalConsole = $container->get(Console::class);

        $this->console->call(fn (Console $console) => $console->writeln('fake console'));

        test($container->get(Console::class))->isNot($originalConsole);

        $this->testsConsoleAfter($container);

        test($container->get(Console::class))->is($originalConsole);
    }

    #[Test]
    public function console_tests_do_not_leak_the_fake_console_into_later_tests(Container $container): void
    {
        $originalConsole = $container->get(Console::class);

        $this->console->call(fn (Console $console) => $console->writeln('fake console'));
        $this->testsConsoleAfter($container);
        $this->testsConsoleBefore($container);

        test($this->originalConsole)->is($originalConsole);
        test($container->get(Console::class))->is($originalConsole);
    }
}
