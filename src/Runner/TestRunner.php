<?php

namespace Tempest\Testing\Runner;

use Symfony\Component\Process\Process;
use Tempest\Core\Environment;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Testing\Test;

use function Tempest\EventBus\event;

final class TestRunner
{
    public function __construct(
        public readonly string $name = 'default',
        public readonly bool $debug = false,
    ) {}

    private ?Process $process = null;

    /** @param ImmutableArray<array-key, \Tempest\Testing\Test> $tests */
    public function run(ImmutableArray $tests): self
    {
        $tests = $tests->map(fn (Test $test) => '--tests="' . $test->name . '"');

        $command = [
            PHP_BINDIR . '/php',
            'tempest',
            'test:run',
            $this->name,
            ...$tests,
        ];

        if ($this->debug) {
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
                    if ($this->debug) {
                        echo $line . PHP_EOL;
                    }

                    $payload = json_decode(substr($line, strlen('[EVENT] ')), true);

                    if (! is_array($payload) || ! is_string($payload['event'] ?? null) || ! array_key_exists('data', $payload)) {
                        continue;
                    }

                    $eventClass = $payload['event'];
                    $event = $eventClass::deserialize($payload['data']);

                    event($event);
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
}
