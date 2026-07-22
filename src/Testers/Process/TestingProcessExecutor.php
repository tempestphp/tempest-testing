<?php

namespace Tempest\Testing\Testers\Process;

use RuntimeException;
use Tempest\Process\GenericProcessExecutor;
use Tempest\Process\InvokedProcess;
use Tempest\Process\InvokedSystemProcess;
use Tempest\Process\PendingProcess;
use Tempest\Process\Pool;
use Tempest\Process\ProcessExecutor;
use Tempest\Process\ProcessPoolResults;
use Tempest\Process\ProcessResult;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Support\Regex;

final class TestingProcessExecutor implements ProcessExecutor
{
    /** @var array<string,list<array{PendingProcess,ProcessResult}>> */
    private(set) array $executions = [];

    /** @param array<string, string|ProcessResult|InvokedProcessDescription> $mocks */
    public function __construct(
        private readonly GenericProcessExecutor $executor,
        public array $mocks = [],
        public bool $allowRunningActualProcesses = false,
    ) {}

    public function run(string|PendingProcess $command): ProcessResult
    {
        if (($result = $this->findMockedProcess($command)) instanceof ProcessResult) {
            return $this->recordExecution($command, $result);
        }

        if (! $this->allowRunningActualProcesses) {
            throw ProcessExecutionWasForbidden::forPendingProcess($command);
        }

        return $this->recordExecution($command, $this->start($command)->wait());
    }

    public function start(string|PendingProcess $command): InvokedProcess
    {
        if (($description = $this->findInvokedProcessDescription($command)) instanceof InvokedProcessDescription) {
            $this->recordExecution($command, $process = new InvokedTestingProcess($description));

            return $process;
        }

        if (! $this->allowRunningActualProcesses) {
            throw ProcessExecutionWasForbidden::forPendingProcess($command);
        }

        $this->recordExecution($command, $process = $this->executor->start($command));

        return $process;
    }

    /** @param iterable<PendingProcess|string> $pool */
    public function pool(iterable $pool): Pool
    {
        /** @var list<PendingProcess> $pendingProcesses */
        $pendingProcesses = [];

        foreach ($pool as $process) {
            $pendingProcesses[] = $this->createPendingProcess($process);
        }

        return new Pool(
            pendingProcesses: new ImmutableArray($pendingProcesses), // @mago-expect analysis:less-specific-nested-argument-type
            processExecutor: $this,
        );
    }

    /** @param iterable<PendingProcess|string> $pool */
    public function concurrently(iterable $pool): ProcessPoolResults
    {
        return $this->pool($pool)->start()->wait();
    }

    public function commandMatchesPattern(string $command, string $pattern): bool
    {
        return Regex\matches($command, $this->buildRegExpFromString($pattern));
    }

    private function findMockedProcess(string|PendingProcess $command): ?ProcessResult
    {
        $process = $this->createPendingProcess($command);
        $command = $this->commandToString($process);

        foreach ($this->mocks as $pattern => $result) {
            if (! Regex\matches($command, $this->buildRegExpFromString($pattern))) {
                continue;
            }

            if ($result instanceof ProcessResult) {
                return $result;
            }

            if ($result instanceof InvokedProcessDescription) {
                return $result->resolveResult();
            }

            return new ProcessResult(exitCode: 0, output: $result, errorOutput: '');
        }

        return null;
    }

    private function findInvokedProcessDescription(string|PendingProcess $command): ?InvokedProcessDescription
    {
        $process = $this->createPendingProcess($command);
        $command = $this->commandToString($process);

        foreach ($this->mocks as $pattern => $result) {
            if (! $this->commandMatchesPattern($command, $pattern)) {
                continue;
            }

            if ($result instanceof InvokedProcessDescription) {
                return $result;
            }

            return new InvokedProcessDescription();
        }

        return null;
    }

    private function recordExecution(string|PendingProcess $command, InvokedProcess|ProcessResult $result): ProcessResult
    {
        $process = $this->createPendingProcess($command);
        $command = $this->commandToString($process);
        $result = match (true) {
            $result instanceof ProcessResult => $result,
            $result instanceof InvokedTestingProcess => $result->getProcessResult(),
            $result instanceof InvokedSystemProcess => $result->wait(),
            default => throw new RuntimeException('Unexpected result type.'),
        };

        $this->executions[$command] ??= [];
        $this->executions[$command][] = [$process, $result];

        return $result;
    }

    /** @return non-empty-string */
    private function buildRegExpFromString(string $string): string
    {
        return sprintf('/%s/', str_replace('\\*', '.*', preg_quote($string, delimiter: '/')));
    }

    private function createPendingProcess(string|PendingProcess $processOrCommand): PendingProcess
    {
        if ($processOrCommand instanceof PendingProcess) {
            return $processOrCommand;
        }

        return new PendingProcess(command: $processOrCommand);
    }

    private function commandToString(PendingProcess $process): string
    {
        if (is_string($process->command)) {
            return $process->command;
        }

        return implode(' ', array_map(strval(...), $process->command));
    }
}
