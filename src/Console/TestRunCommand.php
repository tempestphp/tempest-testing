<?php

namespace Tempest\Testing\Console;

use ArrayIterator;
use ReflectionException;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\EventBus\EventBusConfig;
use Tempest\Testing\Actions\RunTest;
use Tempest\Testing\Events\DispatchToParentProcessMiddleware;
use Tempest\Testing\Test;

final class TestRunCommand
{
    use HasConsole;

    public function __construct(
        private readonly Container $container,
        private readonly EventBusConfig $eventBusConfig,
    ) {}

    #[ConsoleCommand(
        middleware: [WithDiscoveredTestsMiddleware::class],
        hidden: true,
    )]
    public function __invoke(array $tests): void
    {
        $runTest = new RunTest($this->resolveContainer());

        $this->eventBusConfig->middleware->add(DispatchToParentProcessMiddleware::class);

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

    private function resolveContainer(): Container
    {
        $container = $this->container;

        if (! $container instanceof GenericContainer) {
            return $container;
        }

        $clonedSingletons = new ArrayIterator();

        foreach ($container->singletons as $index => $singleton) {
            $clonedSingletons[$index] = is_object($singleton) ? clone $singleton : $singleton;
        }

        $clonedDefinitions = new ArrayIterator();

        foreach ($container->definitions as $index => $definition) {
            $clonedDefinitions[$index] = is_object($definition) ? clone $definition : $definition;
        }

        $clone = new GenericContainer(
            definitions: $clonedDefinitions,
            singletons: $clonedSingletons,
            initializers: $container->initializers,
            dynamicInitializers: $container->dynamicInitializers,
            decorators: $container->decorators,
        );

        $clone->singleton(Container::class, $clone);

        return $clone;
    }
}