<?php

namespace Tempest\Testing\Actions;

use Tempest\Support\Arr\ImmutableArray;
use Tempest\Testing\Events\TestRunEnded;
use Tempest\Testing\Events\TestRunStarted;
use Tempest\Testing\Events\TestsChunked;
use Tempest\Testing\Runner\TestRunner;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;

use function Tempest\EventBus\event;

final class ChunkAndRunTests
{
    public function __construct(
        private TestEnvironment $testEnvironment,
    ) {}

    public function __invoke(ImmutableArray $tests, int $processes): void
    {
        $chunks = max(1, (int) ceil($tests->count() / $processes));

        $tests = $tests
            ->chunk($chunks)
            ->map(function (ImmutableArray $tests, int|string $i): TestRunner { // @mago-expect lint:prefer-arrow-function
                /** @var ImmutableArray<array-key, Test> $tests */
                return new TestRunner(
                    name: (string) $i,
                    testEnvironment: $this->testEnvironment,
                )->run($tests);
            });

        event(new TestsChunked($tests->count()));

        event(new TestRunStarted());

        $tests->map(fn (TestRunner $runner) => $runner->wait());

        event(new TestRunEnded());
    }
}
