<?php

namespace Tempest\Testing\Testers\Process;

use Closure;
use Tempest\DateTime\Duration;
use Tempest\Process\InvokedProcess;
use Tempest\Process\OutputChannel;
use Tempest\Process\ProcessResult;

final class InvokedTestingProcess implements InvokedProcess
{
    public ?int $pid {
        get {
            $this->invokeOutputHandlerWithNextLineOfOutput();

            return $this->description->pid;
        }
    }

    public bool $running {
        get {
            $this->invokeOutputHandlerWithNextLineOfOutput();

            if ($this->remainingRunIterations === 0) {
                $this->invokeOutputHandlerWithRemainingOutput();

                return false;
            }

            $this->remainingRunIterations -= 1;

            return true;
        }
    }

    public string $output {
        get {
            $this->latestOutput();

            $output = [];

            for ($i = 0; $i < $this->nextOutputIndex; $i++) {
                if ($this->description->output[$i]['type'] !== OutputChannel::OUTPUT) {
                    continue;
                }

                $output[] = $this->description->output[$i]['buffer'];
            }

            return rtrim(implode('', $output), "\n") . "\n";
        }
    }

    public string $errorOutput {
        get {
            $this->latestErrorOutput();

            $output = [];

            for ($i = 0; $i < $this->nextErrorOutputIndex; $i++) {
                if ($this->description->output[$i]['type'] !== OutputChannel::ERROR) {
                    continue;
                }

                $output[] = $this->description->output[$i]['buffer'];
            }

            return rtrim(implode('', $output), "\n") . "\n";
        }
    }

    /** @var null|Closure(OutputChannel, string): void */
    private ?Closure $outputHandler = null;

    private int $remainingRunIterations {
        get {
            if (! isset($this->remainingRunIterations)) {
                $this->remainingRunIterations = $this->description->runIterations;
            }

            return $this->remainingRunIterations;
        }
    }

    private int $nextOutputIndex = 0;

    private int $nextErrorOutputIndex = 0;

    public function __construct(
        private readonly InvokedProcessDescription $description,
    ) {}

    public function signal(int $signal): self
    {
        $this->invokeOutputHandlerWithNextLineOfOutput();

        return $this;
    }

    public function stop(float|int|Duration $timeout = 10, ?int $signal = null): self
    {
        $this->remainingRunIterations = 0;

        return $this;
    }

    public function wait(?callable $output = null): ProcessResult
    {
        if ($output !== null) {
            $this->outputHandler = $output instanceof Closure ? $output : Closure::fromCallable($output);
        }

        if (! $this->outputHandler instanceof Closure) {
            $this->remainingRunIterations = 0;

            return $this->getProcessResult();
        }

        $this->invokeOutputHandlerWithRemainingOutput();

        $this->remainingRunIterations = 0;

        return $this->getProcessResult();
    }

    public function latestErrorOutput(): string
    {
        $outputCount = count($this->description->output);
        $output = '';

        for ($i = $this->nextErrorOutputIndex; $i < $outputCount; $i++) {
            if ($this->description->output[$i]['type'] === OutputChannel::ERROR) {
                $output = $this->description->output[$i]['buffer'];
                $this->nextErrorOutputIndex = $i + 1;

                break;
            }

            $this->nextErrorOutputIndex = $i + 1;
        }

        return $output;
    }

    public function getProcessResult(): ProcessResult
    {
        return $this->description->resolveResult();
    }

    private function latestOutput(): string
    {
        $outputCount = count($this->description->output);
        $output = '';

        for ($i = $this->nextOutputIndex; $i < $outputCount; $i++) {
            if ($this->description->output[$i]['type'] === OutputChannel::OUTPUT) {
                $output = $this->description->output[$i]['buffer'];
                $this->nextOutputIndex = $i + 1;

                break;
            }

            $this->nextOutputIndex = $i + 1;
        }

        return $output;
    }

    private function invokeOutputHandlerWithNextLineOfOutput(): bool
    {
        if (! $this->outputHandler instanceof Closure) {
            return false;
        }

        [$outputCount, $outputStartingPoint] = [
            count($this->description->output),
            min($this->nextOutputIndex, $this->nextErrorOutputIndex),
        ];

        for ($i = $outputStartingPoint; $i < $outputCount; $i++) {
            $currentOutput = $this->description->output[$i];

            if ($currentOutput['type'] === OutputChannel::OUTPUT && $i >= $this->nextOutputIndex) {
                call_user_func($this->outputHandler, OutputChannel::OUTPUT, $currentOutput['buffer']);

                $this->nextOutputIndex = $i + 1;

                return true;
            }

            if ($currentOutput['type'] === OutputChannel::ERROR && $i >= $this->nextErrorOutputIndex) {
                call_user_func($this->outputHandler, OutputChannel::ERROR, $currentOutput['buffer']);

                $this->nextErrorOutputIndex = $i + 1;

                return true;
            }
        }

        return false;
    }

    private function invokeOutputHandlerWithRemainingOutput(): void
    {
        if (! $this->invokeOutputHandlerWithNextLineOfOutput()) {
            return;
        }

        $this->invokeOutputHandlerWithRemainingOutput();
    }
}
