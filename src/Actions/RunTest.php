<?php

namespace Tempest\Testing\Actions;

use Tempest\Container\Container;
use Tempest\Testing\Events\TestAfterExecuted;
use Tempest\Testing\Events\TestBeforeExecuted;
use Tempest\Testing\Events\TestFailed;
use Tempest\Testing\Events\TestSucceeded;
use Tempest\Testing\Exceptions\TestHasFailed;
use Tempest\Testing\Test;
use function Tempest\event;

final readonly class RunTest
{
    public function __construct(
        private Container $container
    ) {}

    public function __invoke(Test $test): void
    {
        $instance = $this->container->get($test->handler->getDeclaringClass()->getName());

        try {
            $this->runBefore($test, $instance);

            $this->container->invoke($test->handler->getReflection()->getClosure($instance));

            $this->runAfter($test, $instance);

            event(new TestSucceeded($test->name));
        } catch (TestHasFailed $exception) {
            $this->runAfter($test, $instance);

            event(TestFailed::fromException($test->name, $exception));
        }
    }

    private function runBefore(Test $test, object $instance): void
    {
        foreach ($test->before as $before) {
            $this->container->invoke($before->getReflection()->getClosure($instance));

            event(new TestBeforeExecuted($test, $before));
        }
    }

    private function runAfter(Test $test, object $instance): void
    {
        foreach ($test->after as $after) {
            $this->container->invoke($after->getReflection()->getClosure($instance));

            event(new TestAfterExecuted($test, $after));
        }
    }
}