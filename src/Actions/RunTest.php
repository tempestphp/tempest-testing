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
        try {
            foreach ($test->before as $before) {
                $this->container->invoke($before);

                event(new TestBeforeExecuted($test, $before));
            }

            $this->container->invoke($test->handler);

            foreach ($test->after as $after) {
                $this->container->invoke($after);

                event(new TestAfterExecuted($test, $after));
            }

            event(new TestSucceeded($test->name));
        } catch (TestHasFailed $exception) {
            event(TestFailed::fromException($test->name, $exception));
        }
    }
}