<?php

namespace Tempest\Testing\Actions;

use Closure;
use Generator;
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
        private readonly ?Closure $outputHandler = null,
    ) {}

    public function __invoke(ImmutableArray $tests, int $processes): void
    {
        foreach ($this->runWithUpdates($tests, $processes) as $update) {
            unset($update);
        }
    }

    public function runWithUpdates(ImmutableArray $tests, int $processes): Generator
    {
        $chunks = max(1, (int) ceil($tests->count() / $processes));
        $stopFile = $this->createStopFilePath();

        $tests = $tests
            ->chunk($chunks)
            ->map(function (ImmutableArray $tests, int|string $i) use ($stopFile): TestRunner { // @mago-expect lint:prefer-arrow-function
                /** @var ImmutableArray<array-key, Test> $tests */
                return new TestRunner(
                    name: (string) $i,
                    testEnvironment: $this->testEnvironment,
                    outputHandler: $this->outputHandler,
                    stopFile: $stopFile,
                )->run($tests);
            });

        event(new TestsChunked($tests->count()));

        event(new TestRunStarted());

        yield;

        do {
            $running = false;
            $updated = false;
            $failed = false;

            foreach ($tests as $runner) {
                $updated = $runner->tick() || $updated;
                $failed = $runner->failed() || $failed;
                $running = $runner->isRunning() || $running;
            }

            if ($failed) {
                foreach ($tests as $runner) {
                    $runner->stop();
                }

                $updated = true;
            }

            if ($updated || ! $this->testEnvironment->verbose) {
                yield;
            }

            if ($running) {
                usleep(80_000);
            }
        } while ($running);

        $tests->map(fn (TestRunner $runner) => $runner->wait());

        event(new TestRunEnded());

        if (file_exists($stopFile)) {
            unlink($stopFile);
        }

        yield;
    }

    private function createStopFilePath(): string
    {
        return sprintf(
            '%s/tempest-testing-fail-fast-%d-%s',
            sys_get_temp_dir(),
            getmypid(),
            bin2hex(random_bytes(6)),
        );
    }
}
