<?php

namespace Tempest\Testing\Testers\Console;

use Tempest\Console\Console;
use Tempest\Container\Container;
use Tempest\Testing\After;
use Tempest\Testing\Before;

trait TestsConsole
{
    protected ConsoleTester $console;

    protected Console $originalConsole;

    #[Before]
    public function testsConsoleBefore(Container $container): void
    {
        $this->originalConsole = $container->get(Console::class);

        $this->console = new ConsoleTester($container);
    }

    #[After]
    public function testsConsoleAfter(Container $container): void
    {
//        $container->singleton(Console::class, $this->originalConsole);
    }
}