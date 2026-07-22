<?php

namespace Tempest\Testing\Testers\Process;

use Tempest\Process\OutputChannel;
use Tempest\Process\ProcessResult;

final class InvokedProcessDescription
{
    public ?int $pid = 1000;

    /** @var array<int, array{type: OutputChannel, buffer: string}> */
    public array $output = [];

    public int $exitCode = 0;

    public int $runIterations = 1;

    public function pid(int $pid): self
    {
        $this->pid = $pid;

        return $this;
    }

    public function output(string|array $output): self
    {
        if (is_string($output)) {
            $output = [$output];
        }

        foreach ($output as $item) {
            $this->output[] = [
                'type' => OutputChannel::OUTPUT,
                'buffer' => rtrim($item, "\n") . "\n",
            ];
        }

        return $this;
    }

    public function errorOutput(string|array $errorOutput): self
    {
        if (is_string($errorOutput)) {
            $errorOutput = [$errorOutput];
        }

        foreach ($errorOutput as $item) {
            $this->output[] = [
                'type' => OutputChannel::ERROR,
                'buffer' => rtrim($item, "\n") . "\n",
            ];
        }

        return $this;
    }

    public function exitCode(int $exitCode): self
    {
        $this->exitCode = $exitCode;

        return $this;
    }

    public function iterations(int $iterations): self
    {
        $this->runIterations = $iterations;

        return $this;
    }

    public function resolveOutput(bool $error = false): string
    {
        $expectedType = $error ? OutputChannel::ERROR : OutputChannel::OUTPUT;
        $output = [];

        foreach ($this->output as $item) {
            if ($item['type'] !== $expectedType) {
                continue;
            }

            $output[] = rtrim($item['buffer'], "\n");
        }

        if ($output === []) {
            return '';
        }

        return implode("\n", $output) . "\n";
    }

    public function resolveResult(): ProcessResult
    {
        return new ProcessResult(
            exitCode: $this->exitCode,
            output: $this->resolveOutput(error: false),
            errorOutput: $this->resolveOutput(error: true),
        );
    }
}
