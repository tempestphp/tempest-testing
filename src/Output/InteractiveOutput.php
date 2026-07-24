<?php

namespace Tempest\Testing\Output;

use Closure;
use Generator;
use Tempest\Console\Components\ComponentState;
use Tempest\Console\Components\Concerns\HasErrors;
use Tempest\Console\Components\Concerns\HasState;
use Tempest\Console\Components\Renderers\SpinnerRenderer;
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

    private ?string $currentTest = null;

    private bool $finished = false;

    /** @var string[] */
    private array $lines = [];

    /** @var string[] */
    private array $pendingLines = [];

    private string $activeBody = '';

    private ?string $activeFooter = null;

    private bool $bodyChanged = false;

    private SpinnerRenderer $spinner;

    /** @param Closure(self): iterable $runner */
    public function __construct(
        private readonly Closure $runner,
    ) {
        $this->result = new TestResult();
        $this->spinner = new SpinnerRenderer();
    }

    public function render(Terminal $terminal): Generator
    {
        yield $this->renderBody();

        foreach (($this->runner)($this) as $_) {
            yield $this->renderBody();
        }

        $this->finished = true;

        yield $this->renderBody();

        $this->setState(ComponentState::DONE);

        return $this->result->failed === 0;
    }

    public function renderFooter(Terminal $terminal): ?string
    {
        if (! $this->finished && ! $this->bodyChanged && $this->activeFooter !== null) {
            return $this->activeFooter;
        }

        $this->activeFooter = implode(PHP_EOL, [
            $this->renderStatusLine(),
            $this->renderSummary($terminal),
        ]);

        return $this->activeFooter;
    }

    public function appendProcessOutput(string $line): void
    {
        $this->appendLine($line);
    }

    public function onTestsChunked(TestsChunked $event): void
    {
        return;
    }

    public function onTestStarted(TestStarted $event): void
    {
        $this->currentTest = $event->name;
    }

    public function onTestFailed(TestFailed $event): void
    {
        $this->result->addFailed();

        $this->appendLine(sprintf('<style="fg-red">%s</style>', $event->name));
        $this->appendLine(sprintf('  <style="fg-red dim">//</style> <style="fg-red underline">%s</style>', $event->location));
        $this->appendLine(sprintf('  <style="fg-red dim">//</style> <style="fg-red">%s</style>', $event->reason));

        if ($this->testEnvironment->verbose && $event->trace) {
            $this->appendLine($event->trace);
        }

        $this->appendLine('');
    }

    public function onTestSkipped(TestSkipped $event): void
    {
        $this->result->addSkipped();

        $showSkipped = $this->testEnvironment->debug || $this->testEnvironment->skipped && $event->location || $this->testEnvironment->verbose && $event->location;

        if ($showSkipped) {
            $this->appendLine(sprintf('<style="fg-yellow">%s</style>', $event->name));
            $this->appendLine(sprintf('  <style="fg-yellow dim">//</style> <style="fg-yellow underline">%s</style>', $event->location));

            if ($event->reason) {
                $this->appendLine(sprintf('  <style="fg-yellow dim">//</style> <style="fg-yellow">%s</style>', $event->reason));
            }

            $this->appendLine('');
        }
    }

    public function onTestSucceeded(TestSucceeded $event): void
    {
        $this->result->addSucceeded();

        if ($this->testEnvironment->verbose) {
            $this->appendLine(sprintf('<style="fg-green">%s</style>', $event->name));
        }
    }

    public function onTestFinished(TestFinished $event): void
    {
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

    private function renderBody(): string
    {
        $this->bodyChanged = $this->pendingLines !== [] || $this->finished;

        if ($this->pendingLines !== []) {
            $this->activeBody = implode(PHP_EOL, $this->pendingLines);
            $this->pendingLines = [];
        }

        if ($this->finished) {
            return implode(PHP_EOL, $this->lines);
        }

        return $this->activeBody;
    }

    private function renderStatusLine(): string
    {
        if ($this->finished) {
            return sprintf('%s<style="bold bg-green"> All done. </style>', self::FOOTER_PREFIX);
        }

        if ($this->currentTest === null) {
            return self::FOOTER_PREFIX;
        }

        return sprintf('%s<style="dim">current:</style> %s', self::FOOTER_PREFIX, $this->currentTest);
    }

    private function renderSummary(?Terminal $terminal = null): string
    {
        $spinner = $terminal === null || $this->finished || $this->getState()->isFinished()
            ? self::FOOTER_PREFIX
            : sprintf('<style="fg-gray">%s</style> ', $this->spinner->render($terminal, $this->getState()));

        return sprintf(
            '%s<style="bg-green"> %d succeeded </style> <style="bg-red"> %d failed </style> <style="bg-yellow"> %d skipped </style> <style="bg-blue"> %ss </style>',
            $spinner,
            $this->result->succeeded,
            $this->result->failed,
            $this->result->skipped,
            $this->result->elapsedTime,
        );
    }

    private function appendLine(string $line): void
    {
        $this->lines[] = $line;
        $this->pendingLines[] = $line;
    }
}
