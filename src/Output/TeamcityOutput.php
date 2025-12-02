<?php

namespace Tempest\Testing\Output;

use Tempest\Console\HasConsole;
use Tempest\Testing\Events\TestFailed;
use Tempest\Testing\Events\TestRunEnded;
use Tempest\Testing\Events\TestRunStarted;
use Tempest\Testing\Events\TestsChunked;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Events\TestStarted;
use Tempest\Testing\Events\TestSucceeded;
use Tempest\Testing\Runner\TestResult;
use Tempest\Testing\TestOutput;
use function Tempest\Support\str;

final class TeamcityOutput implements TestOutput
{
    use HasConsole;

    public function __construct(
        public bool $verbose = false,
        private TestResult $result = new TestResult(),
    ) {}

    public function onTestsChunked(TestsChunked $event): void
    {
        return;
    }

    public function onTestStarted(TestStarted $event): void
    {
        $this->writeln("##teamcity[testStarted name='{$event->name}']");
    }

    public function onTestFailed(TestFailed $event): void
    {
        $this->writeln("##teamcity[testFailed name='{$event->name}']");
    }

    public function onTestSkipped(TestSkipped $event): void
    {
        $this->writeln("##teamcity[testSkipped name='{$event->name}']");
    }

    public function onTestSucceeded(TestSucceeded $event): void
    {
        $this->writeln("##teamcity[testFinished name='{$event->name}']");
    }

    public function onTestRunStarted(TestRunStarted $event): void
    {
        $this->writeln("##teamcity[testSuiteStarted]");
    }

    public function onTestRunEnded(TestRunEnded $event): void
    {
        $this->writeln("##teamcity[testSuiteFinished]");
    }
}