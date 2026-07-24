<?php

namespace Tempest\Testing\Tests;

use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Process\Process;
use Tempest\Reflection\MethodReflector;
use Tempest\Testing\Actions\ChunkAndRunTests;
use Tempest\Testing\Events\TestFailed;
use Tempest\Testing\Runner\TestRunner;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;
use Tempest\Testing\Testers\EventBus\TestsEvents;

use function Tempest\Support\arr;
use function Tempest\Testing\test;

final class FailFastTest
{
    use TestsEvents;

    #[Test]
    public function test_run_command_fail_fast_stops_after_the_first_failure(): void
    {
        $this->withFailFastLog(function (string $logFile): void {
            $this->runTestRunProcess([
                FailFastFixture::class . '::fail',
                FailFastFixture::class . '::pass',
            ]);

            $log = $this->readLog($logFile);

            test($log)->contains('fail');
            test($log)->containsNot('pass');
        });
    }

    #[Test]
    public function test_run_command_honors_an_existing_stop_file(): void
    {
        $this->withFailFastLog(function (string $logFile): void {
            $stopFile = $this->temporaryFile();
            file_put_contents($stopFile, '1');

            try {
                $this->runTestRunProcess(
                    tests: [
                        FailFastFixture::class . '::pass',
                    ],
                    stopFile: $stopFile,
                );

                test($this->readLog($logFile))->is([]);
            } finally {
                $this->removeFile($stopFile);
            }
        });
    }

    #[Test]
    public function test_run_command_writes_the_stop_file_when_a_test_fails(): void
    {
        $this->withFailFastLog(function (): void {
            $stopFile = $this->temporaryFile();
            $this->removeFile($stopFile);

            try {
                $this->runTestRunProcess(
                    tests: [
                        FailFastFixture::class . '::fail',
                    ],
                    stopFile: $stopFile,
                );

                test(file_exists($stopFile))->is(true);
                test(file_get_contents($stopFile))->is('1');
            } finally {
                $this->removeFile($stopFile);
            }
        });
    }

    #[Test]
    public function chunk_and_run_tests_signals_other_runners_and_removes_the_stop_file(): void
    {
        $this->events->preventPropagation();

        $this->withFailFastLog(function (string $logFile): void {
            $pattern = sys_get_temp_dir() . '/tempest-testing-fail-fast-' . $this->processId() . '-*';
            $before = glob($pattern) ?: [];

            $tests = arr([
                $this->testFor('fail'),
                $this->testFor('passInSameRunner'),
                $this->testFor('slow'),
                $this->testFor('passAfterSlow'),
            ]);

            iterator_to_array(new ChunkAndRunTests(
                testEnvironment: new TestEnvironment(failFast: true),
                outputHandler: function (): void {},
            )->runWithUpdates($tests, processes: 2));

            $after = glob($pattern) ?: [];
            $log = $this->readLog($logFile);

            test($after)->is($before);
            test($log)->contains('fail');
            test($log)->containsNot('pass-in-same-runner');
            test($log)->containsNot('pass-after-slow');
        });
    }

    #[Test]
    public function test_runner_marks_failed_and_writes_stop_file_when_fail_fast_event_is_seen(): void
    {
        $this->events->preventPropagation();

        $stopFile = $this->temporaryFile();
        $this->removeFile($stopFile);

        try {
            $runner = new TestRunner(
                name: 'runner',
                testEnvironment: new TestEnvironment(failFast: true),
                stopFile: $stopFile,
            );
            $payload = json_encode([
                'event' => TestFailed::class,
                'data' => [
                    'name' => 'Tests\ExampleTest::it_fails',
                    'reason' => 'failed',
                    'location' => '/tests/ExampleTest.php:10',
                    'trace' => null,
                ],
            ]);

            if ($payload === false) {
                test()->fail('Could not encode TestFailed event payload.');
            }

            $this->handleOutputLine(
                $runner,
                '[EVENT] ' . $payload,
            );

            test($runner->failed())->is(true);
            test(file_exists($stopFile))->is(true);
            test(file_get_contents($stopFile))->is('1');
        } finally {
            $this->removeFile($stopFile);
        }
    }

    #[Test]
    public function test_runner_stop_writes_stop_file_and_flushes_partial_output(): void
    {
        $stopFile = $this->temporaryFile();
        $this->removeFile($stopFile);
        $lines = [];

        try {
            $runner = new TestRunner(
                name: 'runner',
                testEnvironment: new TestEnvironment(),
                outputHandler: function (string $line) use (&$lines): void {
                    $lines[] = $line;
                },
                stopFile: $stopFile,
            );

            $this->setRunnerBuffer($runner, 'outputBuffer', 'partial stdout');
            $this->setRunnerBuffer($runner, 'errorOutputBuffer', 'partial stderr');

            $runner->stop();

            test(file_exists($stopFile))->is(true);
            test(file_get_contents($stopFile))->is('1');
            test($lines)->is(['partial stdout', 'partial stderr']);
        } finally {
            $this->removeFile($stopFile);
        }
    }

    #[Test]
    public function test_runner_tick_drains_stdout_and_stderr_on_the_same_tick(): void
    {
        $lines = [];
        $runner = new TestRunner(
            name: 'runner',
            testEnvironment: new TestEnvironment(),
            outputHandler: function (string $line) use (&$lines): void {
                $lines[] = $line;
            },
        );
        $process = new Process([
            PHP_BINDIR . '/php',
            '-r',
            'fwrite(STDOUT, "stdout\n"); fwrite(STDERR, "stderr\n");',
        ]);

        $process->start();
        $process->wait();
        $this->setRunnerProcess($runner, $process);

        test($runner->tick())->is(true);
        test($lines)->is(['stdout', 'stderr']);
    }

    private function testFor(string $method): Test
    {
        return Test::fromReflector(new MethodReflector(new ReflectionMethod(FailFastFixture::class, $method)));
    }

    private function handleOutputLine(TestRunner $runner, string $line): void
    {
        $method = new ReflectionMethod($runner, 'handleOutputLine');
        $method->invoke($runner, $line);
    }

    private function setRunnerBuffer(TestRunner $runner, string $property, string $value): void
    {
        $property = new ReflectionProperty($runner, $property);
        $property->setValue($runner, $value);
    }

    private function setRunnerProcess(TestRunner $runner, Process $process): void
    {
        $property = new ReflectionProperty($runner, 'process');
        $property->setValue($runner, $process);
    }

    /** @param string[] $tests */
    private function runTestRunProcess(array $tests, ?string $stopFile = null): void
    {
        $command = [
            PHP_BINDIR . '/php',
            'tempest',
            'test:run',
            '--name=fail-fast-command',
            '--fail-fast',
        ];

        foreach ($tests as $test) {
            $command[] = '--tests=' . $test;
        }

        $env = [];

        $logFile = getenv('TEMPEST_FAIL_FAST_LOG');

        if (is_string($logFile) && $logFile !== '') {
            $env['TEMPEST_FAIL_FAST_LOG'] = $logFile;
        }

        if ($stopFile !== null) {
            $env['TEMPEST_TESTING_STOP_FILE'] = $stopFile;
        }

        $process = new Process($command, env: $env);
        $process->setTimeout(10);
        $process->run();
    }

    /** @param callable(string): void $callback */
    private function withFailFastLog(callable $callback): void
    {
        $logFile = $this->temporaryFile();
        $this->removeFile($logFile);

        try {
            putenv("TEMPEST_FAIL_FAST_LOG={$logFile}");
            $_ENV['TEMPEST_FAIL_FAST_LOG'] = $logFile;

            $callback($logFile);
        } finally {
            putenv('TEMPEST_FAIL_FAST_LOG');
            unset($_ENV['TEMPEST_FAIL_FAST_LOG']);
            $this->removeFile($logFile);
        }
    }

    /** @return string[] */
    private function readLog(string $logFile): array
    {
        if (! file_exists($logFile)) {
            return [];
        }

        return array_values(array_filter(explode(PHP_EOL, trim((string) file_get_contents($logFile)))));
    }

    private function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tempest-testing-');

        if ($path === false) {
            test()->fail('Could not create temporary file.');
        }

        return $path;
    }

    private function processId(): int
    {
        $processId = getmypid();

        if ($processId === false) {
            test()->fail('Could not determine current process id.');
        }

        return $processId;
    }

    private function removeFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

final class FailFastFixture
{
    public function fail(): void
    {
        $this->log('fail');

        test()->fail('fail fast');
    }

    public function pass(): void
    {
        $this->log('pass');
    }

    public function passInSameRunner(): void
    {
        $this->log('pass-in-same-runner');
    }

    public function slow(): void
    {
        usleep(120_000);

        $this->log('slow');
    }

    public function passAfterSlow(): void
    {
        $this->log('pass-after-slow');
    }

    private function log(string $message): void
    {
        $logFile = getenv('TEMPEST_FAIL_FAST_LOG');

        if (! is_string($logFile) || $logFile === '') {
            return;
        }

        file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND);
    }
}
