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

    private ?string $currentTest = null;

    /** @param Closure(self): iterable $runner */
    public function __construct(
        private readonly Closure $runner,
    ) {
        $this->result = new TestResult();
    }

    public function render(Terminal $terminal): Generator
    {
        yield $this->renderLiveBody($terminal);

        foreach (($this->runner)($this) as $_) {
            yield $this->renderLiveBody($terminal);
        }

        $this->setState(ComponentState::DONE);

        yield $this->renderFinalBody($terminal);

        return $this->result->failed === 0;
    }

    public function renderFooter(Terminal $terminal): ?string
    {
        return sprintf(
            '%s<style="bg-green"> %d succeeded </style> <style="bg-red"> %d failed </style> <style="bg-yellow"> %d skipped </style>%s <style="bg-blue"> %ss </style>',
            self::FOOTER_PREFIX,
            $this->result->succeeded,
            $this->result->failed,
            $this->result->skipped,
            $this->testEnvironment->slow ? sprintf(' <style="bg-gray"> %d slow </style>', $this->result->slow) : '',
            $this->result->elapsedTime,
        );
    }

    public function appendProcessOutput(string $line): void
    {
        $this->appendBodyLine($line);
    }

    public function onTestsChunked(TestsChunked $event): void {}

    public function onTestStarted(TestStarted $event): void
    {
        $this->currentTest = $event->name;
    }

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

    public function onTestFinished(TestFinished $event): void
    {
        if ($this->testEnvironment->slow && $event->duration >= $this->testEnvironment->slowThreshold) {
            $this->result->addSlow();

            $this->appendBodyLine(sprintf(
                '<style="fg-gray">%s</style>',
                $event->name,
            ));

            $this->appendBodyLine(sprintf('  <style="fg-gray dim">//</style> <style="fg-gray">Took %sms</style>', $event->duration));
            $this->appendBodyLine(sprintf('  <style="fg-gray dim">//</style> <style="fg-gray underline">%s</style>', $event->location));
            $this->appendBodyLine();
        }

        if ($this->currentTest === $event->name) {
            $this->currentTest = null;
        }
    }

    public function onTestRunStarted(TestRunStarted $event): void
    {
        $this->result->startTime();
    }

    public function onTestRunEnded(TestRunEnded $event): void
    {
        $this->result->endTime();
        $this->currentTest = null;
    }

    private function appendBodyLine(string $line = ''): void
    {
        if ($this->body !== '') {
            $this->body .= PHP_EOL;
        }

        $this->body .= $line;
    }

    private function renderLiveBody(Terminal $terminal): string
    {
        $maximumBodyLines = max(0, $terminal->height - 4);

        if ($this->body === '' && $this->currentTest !== null) {
            return sprintf('<style="fg-blue">%s</style>', $this->currentTest);
        }

        if ($this->body === '' || $maximumBodyLines === 0) {
            return '';
        }

        $lines = explode(PHP_EOL, $this->body);

        if ($this->currentTest !== null) {
            $lines[] = sprintf('<style="fg-blue">%s</style>', $this->currentTest);
        }

        if (count($lines) <= $maximumBodyLines) {
            return implode(PHP_EOL, $lines);
        }

        $lines = array_slice($lines, -$maximumBodyLines);

        while ($lines !== [] && ($lines[0] === '' || str_starts_with($lines[0], ' '))) {
            array_shift($lines);
        }

        return implode(PHP_EOL, $lines);
    }

    private function renderFinalBody(Terminal $terminal): string
    {
        $footer = $this->renderFooter($terminal);

        if ($this->body === '') {
            return $footer ?? '';
        }

        if ($footer === null) {
            return $this->body;
        }

        return $this->body . PHP_EOL . $footer;
    }
}
