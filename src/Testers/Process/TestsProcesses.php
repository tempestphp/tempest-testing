<?php

namespace Tempest\Testing\Testers\Process;

use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Testing\After;
use Tempest\Testing\Before;

trait TestsProcesses
{
    protected ProcessTester $process;

    private array $testsProcessesOriginalSingletons = [];

    #[Before]
    public function testsProcessesBefore(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to test processes.');
        }

        $this->testsProcessesOriginalSingletons = $container->getSingletons();

        $this->process = new ProcessTester($container);
    }

    #[After]
    public function testsProcessesAfter(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            return;
        }

        $container->setSingletons($this->testsProcessesOriginalSingletons);
    }
}
