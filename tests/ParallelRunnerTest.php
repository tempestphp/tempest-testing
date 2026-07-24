<?php

namespace Tempest\Testing\Tests;

use Closure;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Process\Process;
use Tempest\Container\Container;
use Tempest\EventBus\EventBus;
use Tempest\Reflection\MethodReflector;
use Tempest\Testing\Actions\ChunkAndRunTests;
use Tempest\Testing\Events\TestFinished;
use Tempest\Testing\Events\TestRunEnded;
use Tempest\Testing\Events\TestRunStarted;
use Tempest\Testing\Events\TestsChunked;
use Tempest\Testing\Runner\TestRunner;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;
use Tempest\Testing\Testers\EventBus\TestsEvents;
use UnitEnum;

use function Tempest\Support\arr;
use function Tempest\Testing\test;

final class ParallelRunnerTest
{
    use TestsEvents;

    #[Test]
    public function chunk_and_run_tests_creates_runners_for_typical_and_oversized_process_counts(Container $container): void
    {
        $events = [];

        $this->withRecordingEventBus($container, $events, function (): void {
            iterator_to_array(new ChunkAndRunTests(
                testEnvironment: new TestEnvironment(),
                outputHandler: function (): void {},
            )->runWithUpdates($this->testsFor(['quiet', 'quietAgain', 'quietThird', 'quietFourth', 'quietFifth']), processes: 2));

            iterator_to_array(new ChunkAndRunTests(
                testEnvironment: new TestEnvironment(),
                outputHandler: function (): void {},
            )->runWithUpdates($this->testsFor(['quiet', 'quietAgain']), processes: 5));
        });

        $chunked = array_values(array_filter(
            $events,
            fn (object $event) => $event instanceof TestsChunked,
        ));

        test($chunked)->hasCount(2);
        test($chunked[0]->processCount)->is(2);
        test($chunked[1]->processCount)->is(2);
    }

    #[Test]
    public function run_with_updates_dispatches_run_events_in_order(Container $container): void
    {
        $events = [];

        $this->withRecordingEventBus($container, $events, function (): void {
            iterator_to_array(new ChunkAndRunTests(
                testEnvironment: new TestEnvironment(),
                outputHandler: function (): void {},
            )->runWithUpdates(arr([]), processes: 2));
        });

        test(array_map(fn (object $event) => $event::class, $events))->is([
            TestsChunked::class,
            TestRunStarted::class,
            TestRunEnded::class,
        ]);
    }

    #[Test]
    public function run_with_updates_yields_on_start_output_updates_and_non_verbose_ticks(Container $container): void
    {
        $events = [];

        $this->withRecordingEventBus($container, $events, function (): void {
            $verboseYieldCount = 0;

            foreach (new ChunkAndRunTests(
                testEnvironment: new TestEnvironment(verbose: true),
                outputHandler: function (): void {},
            )->runWithUpdates($this->testsFor(['writesOutput']), processes: 1) as $update) {
                unset($update);
                $verboseYieldCount++;
            }

            $nonVerboseYieldCount = 0;

            foreach (new ChunkAndRunTests(
                testEnvironment: new TestEnvironment(),
                outputHandler: function (): void {},
            )->runWithUpdates($this->testsFor(['slowQuiet']), processes: 1) as $update) {
                unset($update);
                $nonVerboseYieldCount++;
            }

            test($verboseYieldCount)->greaterThanOrEqual(3);
            test($nonVerboseYieldCount)->greaterThan(3);
        });
    }

    #[Test]
    public function test_runner_build_command_includes_tests_and_forwards_flags(): void
    {
        $runner = new TestRunner(
            name: 'runner-1',
            testEnvironment: new TestEnvironment(verbose: true, debug: true, failFast: true),
        );

        test($runner->buildCommand($this->testsFor(['quiet', 'quietAgain'])))->is([
            PHP_BINDIR . '/php',
            'tempest',
            'test:run',
            '--name=runner-1',
            '--tests="' . ParallelRunnerFixture::class . '::quiet"',
            '--tests="' . ParallelRunnerFixture::class . '::quietAgain"',
            '--debug',
            '--verbose',
            '--fail-fast',
        ]);
    }

    #[Test]
    public function test_runner_run_sets_testing_environment_and_stop_file(): void
    {
        $stopFile = $this->temporaryFile();
        $this->removeFile($stopFile);

        $runner = new TestRunner(
            name: 'runner-env',
            testEnvironment: new TestEnvironment(),
            outputHandler: function (): void {},
            stopFile: $stopFile,
        );

        try {
            $runner->run(arr([]));

            $process = $this->getRunnerProcess($runner);

            test($process->getEnv())->hasKey('ENVIRONMENT');
            test($process->getEnv()['ENVIRONMENT'])->is('testing');
            test($process->getEnv())->hasKey('TEMPEST_TESTING_STOP_FILE');
            test($process->getEnv()['TEMPEST_TESTING_STOP_FILE'])->is($stopFile);
        } finally {
            $runner->wait();
            $this->removeFile($stopFile);
        }
    }

    #[Test]
    public function test_runner_preserves_partial_stdout_and_stderr_across_ticks(): void
    {
        $lines = [];
        $runner = new TestRunner(
            name: 'runner-output',
            testEnvironment: new TestEnvironment(),
            outputHandler: function (string $line) use (&$lines): void {
                $lines[] = $line;
            },
        );

        test($this->consumeOutput($runner, 'std', 'outputBuffer'))->is(false);
        test($this->getRunnerBuffer($runner, 'outputBuffer'))->is('std');
        test($lines)->is([]);

        test($this->consumeOutput($runner, "out\nnext\npartial", 'outputBuffer'))->is(true);
        test($this->getRunnerBuffer($runner, 'outputBuffer'))->is('partial');
        test($lines)->is(['stdout', 'next']);

        test($this->consumeOutput($runner, " error\n", 'errorOutputBuffer'))->is(true);
        test($this->getRunnerBuffer($runner, 'errorOutputBuffer'))->is('');
        test($lines)->is(['stdout', 'next', ' error']);
    }

    #[Test]
    public function test_runner_ignores_malformed_event_payloads_without_crashing(): void
    {
        $runner = new TestRunner(
            name: 'runner-events',
            testEnvironment: new TestEnvironment(),
            outputHandler: function (): void {},
        );

        test(fn () => $this->handleOutputLine($runner, '[EVENT] not-json'))->succeeds();
        test(fn () => $this->handleOutputLine($runner, '[EVENT] {"event":123,"data":[]}'))->succeeds();
        test(fn () => $this->handleOutputLine($runner, '[EVENT] {"event":"Not\\\\A\\\\Class","data":[]}'))->succeeds();
    }

    #[Test]
    public function test_runner_writes_and_echoes_raw_process_output(): void
    {
        $lines = [];
        $runner = new TestRunner(
            name: 'runner-handler',
            testEnvironment: new TestEnvironment(),
            outputHandler: function (string $line) use (&$lines): void {
                $lines[] = $line;
            },
        );

        $this->handleOutputLine($runner, 'raw output');

        test($lines)->is(['raw output']);

        $echoingRunner = new TestRunner(
            name: 'runner-echo',
            testEnvironment: new TestEnvironment(),
        );

        ob_start();

        try {
            $this->handleOutputLine($echoingRunner, 'raw echo');
            $output = ob_get_clean();
        } catch (\Throwable $throwable) {
            ob_end_clean();

            throw $throwable;
        }

        test($output)->is('raw echo' . PHP_EOL);
    }

    #[Test]
    public function test_runner_mirrors_event_lines_only_in_debug_mode(): void
    {
        $payload = $this->eventPayload(TestFinished::class, [
            'name' => ParallelRunnerFixture::class . '::quiet',
        ]);
        $normalLines = [];
        $debugLines = [];

        $this->events->preventPropagation();

        $this->handleOutputLine(new TestRunner(
            name: 'normal',
            testEnvironment: new TestEnvironment(),
            outputHandler: function (string $line) use (&$normalLines): void {
                $normalLines[] = $line;
            },
        ), $payload);

        $this->handleOutputLine(new TestRunner(
            name: 'debug',
            testEnvironment: new TestEnvironment(debug: true),
            outputHandler: function (string $line) use (&$debugLines): void {
                $debugLines[] = $line;
            },
        ), $payload);

        test($normalLines)->is([]);
        test($debugLines)->is([$payload]);
    }

    #[Test]
    public function test_runner_wait_drains_remaining_partial_stdout_and_stderr_after_process_exit(): void
    {
        $lines = [];
        $runner = new TestRunner(
            name: 'runner-wait',
            testEnvironment: new TestEnvironment(),
            outputHandler: function (string $line) use (&$lines): void {
                $lines[] = $line;
            },
        );

        $process = new Process([
            PHP_BINDIR . '/php',
            '-r',
            '',
        ]);

        $process->run();
        $this->setRunnerProcess($runner, $process);
        $this->setRunnerBuffer($runner, 'outputBuffer', 'partial stdout');
        $this->setRunnerBuffer($runner, 'errorOutputBuffer', 'partial stderr');

        $runner->wait();

        test($lines)->is(['partial stdout', 'partial stderr']);
    }

    /** @param non-empty-list<string> $methods */
    private function testsFor(array $methods): \Tempest\Support\Arr\ImmutableArray
    {
        return arr(array_map(
            fn (string $method) => Test::fromReflector(new MethodReflector(new ReflectionMethod(ParallelRunnerFixture::class, $method))),
            $methods,
        ));
    }

    /** @param array<int, object> $events */
    private function withRecordingEventBus(Container $container, array &$events, Closure $callback): void
    {
        $originalEventBus = $container->get(EventBus::class);
        $eventBus = new class($events) implements EventBus {
            /** @param array<int, object> $events */
            public function __construct(
                private array &$events,
            ) {}

            public function dispatch(object|string $event): void
            {
                if (is_object($event)) {
                    $this->events[] = $event;
                }
            }

            public function listen(Closure $handler, string|UnitEnum|null $event = null): void
            {
                unset($handler, $event);
            }
        };

        $container->singleton(EventBus::class, $eventBus);

        try {
            $callback();
        } finally {
            $container->singleton(EventBus::class, $originalEventBus);
        }
    }

    /** @param class-string $eventClass */
    private function eventPayload(string $eventClass, array $data): string
    {
        $payload = json_encode([
            'event' => $eventClass,
            'data' => $data,
        ]);

        if ($payload === false) {
            test()->fail('Could not encode event payload.');
        }

        return '[EVENT] ' . $payload;
    }

    private function handleOutputLine(TestRunner $runner, string $line): void
    {
        $method = new ReflectionMethod($runner, 'handleOutputLine');
        $method->invoke($runner, $line);
    }

    private function consumeOutput(TestRunner $runner, string $buffer, string $propertyName): bool
    {
        $pending = $this->getRunnerBuffer($runner, $propertyName);
        $method = new ReflectionMethod($runner, 'consumeOutput');
        $updated = $method->invokeArgs($runner, [$buffer, &$pending]);
        $this->setRunnerBuffer($runner, $propertyName, $pending);

        return $updated;
    }

    private function getRunnerProcess(TestRunner $runner): Process
    {
        $property = new ReflectionProperty($runner, 'process');
        $process = $property->getValue($runner);

        if (! $process instanceof Process) {
            test()->fail('Runner process was not started.');
        }

        return $process;
    }

    private function setRunnerProcess(TestRunner $runner, Process $process): void
    {
        $property = new ReflectionProperty($runner, 'process');
        $property->setValue($runner, $process);
    }

    private function getRunnerBuffer(TestRunner $runner, string $propertyName): string
    {
        $property = new ReflectionProperty($runner, $propertyName);
        $buffer = $property->getValue($runner);

        if (! is_string($buffer)) {
            test()->fail('Runner buffer was not a string.');
        }

        return $buffer;
    }

    private function setRunnerBuffer(TestRunner $runner, string $propertyName, string $value): void
    {
        $property = new ReflectionProperty($runner, $propertyName);
        $property->setValue($runner, $value);
    }

    private function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tempest-testing-');

        if ($path === false) {
            test()->fail('Could not create temporary file.');
        }

        return $path;
    }

    private function removeFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

final class ParallelRunnerFixture
{
    public function quiet(): void {}

    public function quietAgain(): void {}

    public function quietThird(): void {}

    public function quietFourth(): void {}

    public function quietFifth(): void {}

    public function writesOutput(): void
    {
        echo 'runner output' . PHP_EOL;
    }

    public function slowQuiet(): void
    {
        usleep(300_000);
    }
}
