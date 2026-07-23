<?php

namespace Tempest\Testing\Runner;

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
    ) {}

    private ?Process $process = null;

    /** @param ImmutableArray<array-key, \Tempest\Testing\Test> $tests */
    public function run(ImmutableArray $tests): self
    {
        $command = $this->buildCommand($tests);

        if ($this->testEnvironment->debug) {
            echo '> ENVIRONMENT=testing ' . implode(' ', $command) . PHP_EOL;
        }

        $this->process = new Process($command, env: [
            'ENVIRONMENT' => Environment::TESTING->value,
        ]);

        $this->process->start(function (string $type, string $buffer) {
            foreach (explode(PHP_EOL, trim($buffer)) as $line) {
                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, '[EVENT]')) {
                    if ($this->testEnvironment->debug) {
                        echo $line . PHP_EOL;
                    }

                    $payload = json_decode(substr($line, strlen('[EVENT] ')), true);

                    if (! is_array($payload) || ! is_string($payload['event'] ?? null) || ! array_key_exists('data', $payload)) {
                        continue;
                    }

                    $eventClass = $payload['event'];
                    $event = $eventClass::deserialize($payload['data']);

                    event($event);

                    if ($this->testEnvironment->failFast && $event instanceof TestFailed) {
                        return; // TODO
                    }
                } else {
                    echo $line . PHP_EOL;
                }
            }
        });

        return $this;
    }

    public function wait(): self
    {
        $this->process?->wait();

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
}
