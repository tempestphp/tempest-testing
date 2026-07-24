<?php

namespace Tempest\Testing\Runner;

use Closure;
use Symfony\Component\Process\Process;
use Tempest\Core\Environment;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Testing\Events\TestFailed;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;

use function Tempest\EventBus\event;

final class TestRunner
{
    public function __construct(
        public readonly string $name,
        public readonly TestEnvironment $testEnvironment,
        private readonly ?Closure $outputHandler = null,
        private readonly ?string $stopFile = null,
    ) {}

    private ?Process $process = null;

    private string $outputBuffer = '';

    private string $errorOutputBuffer = '';

    private bool $failed = false;

    /** @param ImmutableArray<array-key, \Tempest\Testing\Test> $tests */
    public function run(ImmutableArray $tests): self
    {
        $command = $this->buildCommand($tests);

        if ($this->testEnvironment->debug) {
            $this->writeOutput('> ENVIRONMENT=testing ' . implode(' ', $command));
        }

        $env = [
            'ENVIRONMENT' => Environment::TESTING->value,
        ];

        if ($this->stopFile !== null) {
            $env['TEMPEST_TESTING_STOP_FILE'] = $this->stopFile;
        }

        $this->process = new Process($command, env: $env);

        $this->process->start();

        return $this;
    }

    public function tick(): bool
    {
        $process = $this->process;

        if (! $process instanceof Process) {
            return false;
        }

        $outputUpdated = $this->consumeOutput($process->getIncrementalOutput(), $this->outputBuffer);
        $errorOutputUpdated = $this->consumeOutput($process->getIncrementalErrorOutput(), $this->errorOutputBuffer);

        return $outputUpdated || $errorOutputUpdated;
    }

    public function failed(): bool
    {
        return $this->failed;
    }

    public function isRunning(): bool
    {
        return $this->process?->isRunning() ?? false;
    }

    public function wait(): self
    {
        while ($this->isRunning()) {
            $this->tick();
            usleep(1000);
        }

        $this->process?->wait();
        $this->tick();
        $this->flushOutput($this->outputBuffer);
        $this->flushOutput($this->errorOutputBuffer);

        return $this;
    }

    public function stop(): self
    {
        if ($this->stopFile !== null) {
            file_put_contents($this->stopFile, '1');
        }

        $this->tick();
        $this->flushOutput($this->outputBuffer);
        $this->flushOutput($this->errorOutputBuffer);

        return $this;
    }

    /** @return string[] */
    public function buildCommand(ImmutableArray $tests): array
    {
        $tests = $tests->map(fn (Test $test) => '--tests="' . $test->name . '"');

        $command = [
            PHP_BINDIR . '/php',
            'tempest',
            'test:run',
            '--name=' . $this->name,
            ...$tests,
        ];

        if ($this->testEnvironment->debug) {
            $command[] = '--debug';
        }

        if ($this->testEnvironment->verbose) {
            $command[] = '--verbose';
        }

        if ($this->testEnvironment->failFast) {
            $command[] = '--fail-fast';
        }

        return $command;
    }

    private function consumeOutput(string $buffer, string &$pending): bool
    {
        if ($buffer === '') {
            return false;
        }

        $pending .= $buffer;

        $lines = preg_split('/\R/', $pending);

        if ($lines === false) {
            return false;
        }

        if (preg_match('/\R$/', $pending) === 1) {
            $pending = '';
        } else {
            $pending = array_pop($lines) ?? '';
        }

        foreach ($lines as $line) {
            $this->handleOutputLine($line);
        }

        return $lines !== [];
    }

    private function flushOutput(string &$pending): void
    {
        if ($pending === '') {
            return;
        }

        $this->handleOutputLine($pending);
        $pending = '';
    }

    private function handleOutputLine(string $line): void
    {
        if ($line === '') {
            return;
        }

        if (str_starts_with($line, '[EVENT]')) {
            if ($this->testEnvironment->debug) {
                $this->writeOutput($line);
            }

            $payload = json_decode(substr($line, strlen('[EVENT] ')), true);

            if (! is_array($payload) || ! is_string($payload['event'] ?? null) || ! array_key_exists('data', $payload)) {
                return;
            }

            $eventClass = $payload['event'];
            $event = $eventClass::deserialize($payload['data']);

            event($event);

            if ($this->testEnvironment->failFast && $event instanceof TestFailed) {
                $this->failed = true;

                if ($this->stopFile !== null) {
                    file_put_contents($this->stopFile, '1');
                }
            }

            return;
        }

        $this->writeOutput($line);
    }

    private function writeOutput(string $line): void
    {
        if ($this->outputHandler) {
            ($this->outputHandler)($line);

            return;
        }

        echo $line . PHP_EOL;
    }
}
