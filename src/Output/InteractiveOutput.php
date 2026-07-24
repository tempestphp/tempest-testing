<?php

namespace Tempest\Testing\Output;

use Closure;
use Generator;
use Tempest\Console\Components\ComponentState;
use Tempest\Console\Components\Concerns\HasErrors;
use Tempest\Console\Components\Concerns\HasState;
use Tempest\Console\InteractiveConsoleComponent;
use Tempest\Console\Terminal\Terminal;
use Tempest\Testing\Events\TestFailed;
use Tempest\Testing\Events\TestFinished;
use Tempest\Testing\Events\TestRunEnded;
use Tempest\Testing\Events\TestRunStarted;
use Tempest\Testing\Events\TestsChunked;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Events\TestStarted;
use Tempest\Testing\Events\TestSucceeded;
use Tempest\Testing\Runner\TestResult;
use Tempest\Testing\TestEnvironment;

final class InteractiveOutput implements InteractiveConsoleComponent, TestOutput
{
    use HasErrors;
    use HasState;

    private const string FOOTER_PREFIX = '  ';

    public TestEnvironment $testEnvironment;

    private TestResult $result;

    private string $body = '';

    /** @param Closure(self): iterable $runner */
    public function __construct(
        private readonly Closure $runner,
    ) {
        $this->result = new TestResult();
    }

    public function render(Terminal $terminal): Generator
    {
        yield $this->body;

        foreach (($this->runner)($this) as $_) {
            yield $this->body;
        }

        yield $this->body;

        $this->setState(ComponentState::DONE);

        return $this->result->failed === 0;
    }

    public function renderFooter(Terminal $terminal): ?string
    {
        return sprintf(
            '%s<style="bg-green"> %d succeeded </style> <style="bg-red"> %d failed </style> <style="bg-yellow"> %d skipped </style> <style="bg-blue"> %ss </style>',
            self::FOOTER_PREFIX,
            $this->result->succeeded,
            $this->result->failed,
            $this->result->skipped,
            $this->result->elapsedTime,
        );
    }

    public function appendProcessOutput(string $line): void
    {
        $this->appendBodyLine($line);
    }

    public function onTestsChunked(TestsChunked $event): void {}

    public function onTestStarted(TestStarted $event): void {}

    public function onTestFailed(TestFailed $event): void
    {
        $this->result->addFailed();

        $this->appendBodyLine(sprintf('<style="fg-red">%s</style>', $event->name));
        $this->appendBodyLine(sprintf('  <style="fg-red dim">//</style> <style="fg-red underline">%s</style>', $event->location));
        $this->appendBodyLine(sprintf('  <style="fg-red dim">//</style> <style="fg-red">%s</style>', $event->reason));

        if ($this->testEnvironment->verbose && $event->trace) {
            $this->appendBodyLine($event->trace);
        }

        $this->appendBodyLine();
    }

    public function onTestSkipped(TestSkipped $event): void
    {
        $this->result->addSkipped();

        $showSkipped = $this->testEnvironment->debug || $this->testEnvironment->skipped && $event->location || $this->testEnvironment->verbose && $event->location;

        if (! $showSkipped) {
            return;
        }

        $this->appendBodyLine(sprintf('<style="fg-yellow">%s</style>', $event->name));

        if ($event->location) {
            $this->appendBodyLine(sprintf('  <style="fg-yellow dim">//</style> <style="fg-yellow underline">%s</style>', $event->location));
        }

        if ($event->reason) {
            $this->appendBodyLine(sprintf('  <style="fg-yellow dim">//</style> <style="fg-yellow">%s</style>', $event->reason));
        }

        $this->appendBodyLine();
    }

    public function onTestSucceeded(TestSucceeded $event): void
    {
        $this->result->addSucceeded();

        if ($this->testEnvironment->verbose) {
            $this->appendBodyLine(sprintf('<style="fg-green">%s</style>', $event->name));
        }
    }

    public function onTestFinished(TestFinished $event): void {}

    public function onTestRunStarted(TestRunStarted $event): void
    {
        $this->result->startTime();
    }

    public function onTestRunEnded(TestRunEnded $event): void
    {
        $this->result->endTime();
    }

    private function appendBodyLine(string $line = ''): void
    {
        if ($this->body !== '') {
            $this->body .= PHP_EOL;
        }

        $this->body .= $line;
    }
}
