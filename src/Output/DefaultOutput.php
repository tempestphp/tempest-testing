<?php

namespace Tempest\Testing\Output;

use Tempest\Console\HasConsole;
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

use function Tempest\Support\str;

final class DefaultOutput implements TestOutput
{
    use HasConsole;

    public TestEnvironment $testEnvironment;

    public function __construct(
        private TestResult $result = new TestResult(),
    ) {}

    public function onTestsChunked(TestsChunked $event): void
    {
        if ($this->testEnvironment->verbose) {
            $this
                ->writeln()
                ->info(sprintf(
                    'will run on %d %s',
                    $event->processCount,
                    str('process')->pluralize($event->processCount),
                ))
                ->writeln();
        }
    }

    public function onTestStarted(TestStarted $event): void
    {
        return;
    }

    public function onTestFailed(TestFailed $event): void
    {
        $this->result->addFailed();

        $this->error(sprintf('<style="fg-red">%s</style>', $event->name));
        $this->writeln(sprintf('  <style="fg-red dim">//</style> <style="fg-red underline">%s</style>', $event->location));
        $this->writeln(sprintf('  <style="fg-red dim">//</style> <style="fg-red">%s</style>', $event->reason));

        if ($this->testEnvironment->verbose && $event->trace) {
            $this->writeln($event->trace);
        }

        $this->writeln();
    }

    public function onTestSkipped(TestSkipped $event): void
    {
        $this->result->addSkipped();

        $showSkipped = $this->testEnvironment->debug || $this->testEnvironment->skipped && $event->location || $this->testEnvironment->verbose && $event->location;

        if ($showSkipped) {
            $this->warning($event->name);
            $this->writeln(sprintf('  <style="fg-yellow dim">//</style> <style="fg-yellow underline">%s</style>', $event->location));

            if ($event->reason) {
                $this->writeln(sprintf('  <style="fg-yellow dim">//</style> <style="fg-yellow">%s</style>', $event->reason));
            }

            $this->writeln();
        }
    }

    public function onTestSucceeded(TestSucceeded $event): void
    {
        $this->result->addSucceeded();

        if ($this->testEnvironment->verbose) {
            $this->success($event->name);
        }
    }

    public function onTestFinished(TestFinished $event): void
    {
        if ($this->testEnvironment->slow && $event->duration >= $this->testEnvironment->slowThreshold) {
            $this->result->addSlow();

            $this->writeln(sprintf(
                '<style="fg-gray">… </style><style="dim fg-gray">//</style> <style="fg-gray">%s</style>',
                $event->name,
            ));

            $this->writeln(sprintf('  <style="fg-gray dim">//</style> <style="fg-gray">Took %sms</style>', $event->duration));
            $this->writeln(sprintf('  <style="fg-gray dim">//</style> <style="fg-gray underline">%s</style>', $event->location));
            $this->writeln();
        }

        return;
    }

    public function onTestRunStarted(TestRunStarted $event): void
    {
        $this->result->startTime();
    }

    public function onTestRunEnded(TestRunEnded $event): void
    {
        $this->result->endTime();

        $message = sprintf(
            '<style="bg-green"> %d succeeded </style> <style="bg-red"> %d failed </style> <style="bg-yellow"> %d skipped </style>%s <style="bg-blue"> %ss </style>',
            $this->result->succeeded,
            $this->result->failed,
            $this->result->skipped,
            $this->testEnvironment->slow ? sprintf(' <style="bg-gray"> %d slow </style>', $this->result->slow) : '',
            $this->result->elapsedTime,
        );

        if ($this->result->failed > 0 || $this->testEnvironment->verbose) {
            $this->writeln();
        }

        $this->writeln($message);
    }
}
