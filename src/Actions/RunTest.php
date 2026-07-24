<?php

namespace Tempest\Testing\Actions;

use Psr\Container\ContainerInterface;
use Tempest\Container\Container;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\ParameterReflector;
use Tempest\Testing\Events\TestAfterExecuted;
use Tempest\Testing\Events\TestBeforeExecuted;
use Tempest\Testing\Events\TestFailed;
use Tempest\Testing\Events\TestFinished;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Events\TestStarted;
use Tempest\Testing\Events\TestSucceeded;
use Tempest\Testing\Exceptions\InvalidProviderData;
use Tempest\Testing\Exceptions\TestHasFailed;
use Tempest\Testing\Exceptions\TestWasSkipped;
use Tempest\Testing\Test;
use Throwable;

use function Tempest\EventBus\event;

final class RunTest
{
    public function __construct(
        private ContainerInterface|Container $container,
    ) {}

    public function __invoke(Test $test): bool
    {
        $instance = $this->getInstance($test);

        $providedData = [];

        $providers = $test->provide ?? [[]];

        foreach ($providers as $provider) {
            if (is_array($provider)) {
                $providedData[] = $provider;
                continue;
            }

            if (is_string($provider)) {
                if (! method_exists($instance, $provider)) {
                    throw InvalidProviderData::invalidMethodName($test, $provider);
                }

                // @mago-expect analysis:string-member-selector
                // @mago-expect analysis:impossible-assignment
                $provider = $instance->{$provider}(...);
            }

            if (is_callable($provider)) {
                // TODO: add DI here as well?
                $provider = $provider();
            }

            if (is_iterable($provider)) {
                foreach ($provider as $data) {
                    if (! is_array($data)) {
                        continue;
                    }

                    $providedData[] = $data;
                }
            }
        }

        foreach ($providedData as $data) {
            if (! $this->runEntry($test, $instance, $data)) {
                return false;
            }
        }

        return true;
    }

    private function runEntry(Test $test, object $instance, array $data): bool
    {
        $start = hrtime(true);

        event(new TestStarted($test->name));

        try {
            $this->runBefore($test, $instance);

            $this->callMethod($instance, $test->handler, $data);

            $this->runAfter($test, $instance);

            event(new TestSucceeded(
                name: $test->name,
            ));

            $passed = true;
        } catch (TestWasSkipped $exception) {
            $this->runAfter($test, $instance);

            event(new TestSkipped(
                name: $test->name,
                reason: $exception->reason,
                location: $test->location,
            ));

            $passed = true;
        } catch (TestHasFailed $exception) {
            $this->runAfter($test, $instance);

            event(TestFailed::fromTestHasFailed(
                test: $test,
                exception: $exception,
            ));

            $passed = false;
        } catch (Throwable $exception) {
            $this->runAfter($test, $instance);

            event(TestFailed::fromThrowable(
                test: $test,
                throwable: $exception,
            ));

            $passed = false;
        }

        event(new TestFinished(
            name: $test->name,
            location: $test->location,
            duration: (hrtime(true) - $start) / 1_000_000,
        ));

        return $passed;
    }

    private function runBefore(Test $test, object $instance): void
    {
        foreach ($test->before as $before) {
            $this->callMethod($instance, $before);

            event(new TestBeforeExecuted($test, $before));
        }
    }

    private function runAfter(Test $test, object $instance): void
    {
        foreach ($test->after as $after) {
            $this->callMethod($instance, $after);

            event(new TestAfterExecuted($test, $after));
        }
    }

    private function getInstance(Test $test): object
    {
        return $this->container->get($test->handler->getDeclaringClass()->getName()); // @mago-expect analysis:mixed-return-statement
    }

    private function callMethod(object $instance, MethodReflector $method, array $data = []): void
    {
        foreach ($method->getParameters() as $parameter) {
            /** @var ParameterReflector $parameter */
            $parameterName = $parameter->getName();

            if (isset($data[$parameterName])) {
                continue;
            }

            if ($parameter->hasDefaultValue()) {
                continue;
            }

            $parameterType = $parameter->getType();

            if ($parameterType->isScalar()) {
                continue;
            }

            $typeName = $parameterType->getName();

            if (! class_exists($typeName) && ! interface_exists($typeName)) {
                continue;
            }

            $data[$parameterName] = $this->container->get($typeName);
        }

        $instance->{$method->getName()}(...$data); // @mago-expect analysis:string-member-selector
    }
}
