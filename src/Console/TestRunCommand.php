<?php

namespace Tempest\Testing\Console;

use ReflectionException;
use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;
use Tempest\Container\Container;
use Tempest\Core\FrameworkKernel;
use Tempest\Core\Kernel;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\EventBus\EventBusConfig;
use Tempest\Http\GenericRequest;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Testing\Actions\RunTest;
use Tempest\Testing\Events\DispatchToParentProcessMiddleware;
use Tempest\Testing\Runner\TestRunner;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;

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
    public function __invoke(
        string $name,
        #[ConsoleArgument(description: 'Show all output, including succeeding and skipped tests', aliases: ['-v'])]
        bool $verbose = false,
        #[ConsoleArgument(description: 'Fail as soon as an error occurs', aliases: ['-f'])]
        bool $failFast = false,
        #[ConsoleArgument(description: 'Show debug output', aliases: ['-d'])]
        bool $debug = false,
        array $tests = [],
    ): void {
        $testEnvironment = new TestEnvironment(
            verbose: $verbose,
            debug: $debug,
            failFast: $failFast,
        );

        $this->container->singleton(TestEnvironment::class, $testEnvironment);

        $testRunner = new TestRunner($name, $testEnvironment);

        $container = $this->resolveContainer($testRunner);

        $runTest = $container->get(RunTest::class);
        $stopFile = getenv('TEMPEST_TESTING_STOP_FILE') ?: null;

        foreach ($tests as $testName) {
            if ($failFast && $stopFile !== null && file_exists($stopFile)) {
                break;
            }

            try {
                $test = Test::fromName($testName);
            } catch (ReflectionException) {
                // Reflection Error, skipping, probably need to provide an error

                continue;
            }

            $passed = $runTest($test);

            if (! $passed && $stopFile !== null) {
                file_put_contents($stopFile, '1');
            }

            if ($failFast && ! $passed) {
                break;
            }
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

        $runTest = new RunTest($container);
        $container->singleton(RunTest::class, $runTest);
        $container->singleton(TestRunner::class, $testRunner);

        $container->get(EventBusConfig::class)->middleware->add(DispatchToParentProcessMiddleware::class);

        $request = new GenericRequest(Method::GET, '/', []);
        $container->singleton(Request::class, fn () => $request);
        $container->singleton(GenericRequest::class, fn () => $request);

        return $kernel->container;
    }
}
