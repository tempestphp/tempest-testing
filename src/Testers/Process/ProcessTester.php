<?php

namespace Tempest\Testing\Testers\Process;

use Closure;
use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\Singleton;
use Tempest\Process\GenericProcessExecutor;
use Tempest\Process\PendingProcess;
use Tempest\Process\ProcessExecutor;
use Tempest\Process\ProcessResult;

use function Tempest\Testing\test;

#[Singleton]
final class ProcessTester
{
    private(set) ?TestingProcessExecutor $executor = null;

    private bool $allowRunningActualProcesses = false;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function recordProcessExecutions(): void
    {
        $this->executor ??= new TestingProcessExecutor(
            executor: new GenericProcessExecutor(),
            mocks: [],
            allowRunningActualProcesses: $this->allowRunningActualProcesses,
        );

        $this->container->singleton(ProcessExecutor::class, $this->executor);
    }

    public function mockProcessResult(string $command = '*', string|ProcessResult|InvokedProcessDescription $result = ''): self
    {
        $this->recordProcessExecutions();

        $this->executor()->mocks[$command] = $result;

        return $this;
    }

    /** @param array<string,string|ProcessResult|InvokedProcessDescription> $results */
    public function mockProcessResults(array $results): self
    {
        $this->recordProcessExecutions();

        foreach ($results as $command => $result) {
            $this->executor()->mocks[$command] = $result;
        }

        return $this;
    }

    public function allowRunningActualProcesses(): void
    {
        $this->allowRunningActualProcesses = true;

        if ($this->executor instanceof TestingProcessExecutor) {
            $this->executor->allowRunningActualProcesses = true;
            return;
        }

        $this->recordProcessExecutions();
    }

    public function preventRunningActualProcesses(): void
    {
        $this->allowRunningActualProcesses = false;

        if ($this->executor instanceof TestingProcessExecutor) {
            $this->executor->allowRunningActualProcesses = false;
            return;
        }

        $this->recordProcessExecutions();
    }

    public function disableProcessExecution(): void
    {
        $this->container->singleton(ProcessExecutor::class, new RestrictedProcessExecutor());
    }

    public function debugExecutedProcesses(): never
    {
        throw new RuntimeException(var_export($this->executor()->executions, true));
    }

    public function describe(): InvokedProcessDescription
    {
        return new InvokedProcessDescription();
    }

    public function assertCommandRan(string $command, ?Closure $callback = null): self
    {
        $executions = $this->findExecutionsByPattern($command);

        test($executions)->isNotEmpty('Expected process with command "%s" to be executed, but it was not.', $command);

        if ($callback instanceof Closure) {
            foreach ($executions as [$process, $result]) {
                $assertion = $callback($result, $process);

                if ($assertion === true) {
                    return $this;
                }

                if ($assertion === false) {
                    test()->fail('Callback for command "%s" returned false.', $process->command);
                }
            }
        }

        return $this;
    }

    public function assertRan(Closure $callback): self
    {
        $executor = $this->executor();

        foreach ($executor->executions as $executions) {
            foreach ($executions as [$process, $result]) {
                $assertion = $callback($process, $result);

                if ($assertion === true) {
                    return $this;
                }

                if ($assertion === false) {
                    test()->fail('Callback for command "%s" returned false.', $process->command);
                }
            }
        }

        test()->fail('Could not find a matching command for the provided callback.');
    }

    public function assertCommandDidNotRun(string|Closure $command): self
    {
        $executor = $this->executor();

        if ($command instanceof Closure) {
            foreach ($executor->executions as $executions) {
                foreach ($executions as [$process, $result]) {
                    if ($command($process, $result) !== true) {
                        continue;
                    }

                    test()->fail('Callback for command "%s" returned true.', $process->command);
                }
            }

            return $this;
        }

        test($this->findExecutionsByPattern($command))
            ->isEmpty('Expected process with command "%s" to not be ran, but it was.', $command);

        return $this;
    }

    public function assertNothingRan(): self
    {
        test($this->executor()->executions)->isEmpty('Expected no processes to be executed, but some were.');

        return $this;
    }

    public function assertRanTimes(string|Closure $command, int $times): self
    {
        $executor = $this->executor();

        if ($command instanceof Closure) {
            $count = 0;
            foreach ($executor->executions as $executions) {
                foreach ($executions as [$process, $result]) {
                    if ($command($process, $result) !== true) {
                        continue;
                    }

                    $count++;
                }
            }
        } else {
            $count = count($this->findExecutionsByPattern($command));
        }

        test($count)->is(
            $times,
            $command instanceof Closure
                ? 'Expected command matching callback to be executed %s times, but it was executed %s times.'
                : 'Expected command "%s" to be executed %s times, but it was executed %s times.',
            ...$command instanceof Closure ? [$times, $count] : [$command, $times, $count],
        );

        return $this;
    }

    /** @return array<array{PendingProcess,ProcessResult}> */
    private function findExecutionsByPattern(string $pattern): array
    {
        $executor = $this->executor();

        $executions = [];

        foreach ($executor->executions as $command => $commandExecutions) {
            if (! $executor->commandMatchesPattern($command, $pattern)) {
                continue;
            }

            foreach ($commandExecutions as $execution) {
                $executions[] = $execution;
            }
        }

        return $executions;
    }

    private function executor(): TestingProcessExecutor
    {
        if ($this->executor instanceof TestingProcessExecutor) {
            return $this->executor;
        }

        test()->fail(
            'Process testing is not set up. Please call `$this->process->recordProcessExecutions()` or `$this->process->registerProcessResult()` before running assertions, or call `$this->process->allowRunningActualProcesses()` to allow actual processes to run.',
        );
    }
}
