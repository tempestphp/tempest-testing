<?php

namespace Tempest\Testing\Console;

use ArrayIterator;
use ReflectionException;
use ReflectionProperty;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Core\FrameworkKernel;
use Tempest\Core\Kernel;
use Tempest\Database\Config\DatabaseConfig;
use Tempest\Database\Database;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\EventBus\EventBusConfig;
use Tempest\Testing\Actions\RunTest;
use Tempest\Testing\Events\DispatchToParentProcessMiddleware;
use Tempest\Testing\Runner\TestRunner;
use Tempest\Testing\Test;
use Tempest\View\ViewConfig;
use function Tempest\env;
use function Tempest\Support\Path\normalize;
use function Tempest\Support\Path\to_absolute_path;

final class TestRunCommand
{
    use HasConsole;

    public function __construct(
        private readonly Container $container,
        private readonly Kernel $kernel,
    ) {}

    #[ConsoleCommand(
        middleware: [WithDiscoveredTestsMiddleware::class],
        hidden: true,
    )]
    public function __invoke(string $name, array $tests): void
    {
        $testRunner = new TestRunner($name);
        $runTest = new RunTest($this->resolveContainer($testRunner));

        foreach ($tests as $testName) {
            try {
                $test = Test::fromName($testName);
            } catch (ReflectionException) {
                // Reflection Error, skipping, probably need to provide an error

                continue;
            }

            $runTest($test);
        }
    }

    private function resolveContainer(TestRunner $testRunner): Container
    {
        // Setup discovery locations
        $discoveryLocations = [];

        $testsPath = to_absolute_path($this->kernel->root, 'tests');

        if (is_dir($testsPath)) {
            $discoveryLocations[] = new DiscoveryLocation('Tests', $testsPath);
        }

        // Boot a new kernel for this testing process
        $kernel = FrameworkKernel::boot(
            root: $this->kernel->root,
            discoveryLocations: $discoveryLocations,
            internalStorage: $this->kernel->root . '/.tempest/test_internal_storage/' . $testRunner->name,
        );

        // Configure the container for this testing process
        $container = $kernel->container;
        $container->singleton(TestRunner::class, $testRunner);
        $container->get(EventBusConfig::class)->middleware->add(DispatchToParentProcessMiddleware::class);

        return $kernel->container;
    }
}
