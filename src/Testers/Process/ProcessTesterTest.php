<?php

namespace Tempest\Testing\Testers\Process;

use Tempest\Container\Container;
use Tempest\Process\OutputChannel;
use Tempest\Process\PendingProcess;
use Tempest\Process\ProcessExecutor;
use Tempest\Process\ProcessResult;
use Tempest\Testing\Test;

use function Tempest\Testing\test;

final class ProcessTesterTest
{
    use TestsProcesses;

    #[Test]
    public function mocks_and_records_process_results(Container $container): void
    {
        $this->process->mockProcessResult('echo *', 'hello');

        $executor = $container->get(ProcessExecutor::class);
        $result = $executor->run('echo hello');

        test($result->output)->is('hello');

        $this->process
            ->assertCommandRan('echo *')
            ->assertCommandDidNotRun('missing *')
            ->assertRanTimes('echo *', 1)
            ->assertRan(fn (PendingProcess $process, ProcessResult $result) => $process->command === 'echo hello' && $result->output === 'hello');
    }

    #[Test]
    public function forbids_unmocked_processes(Container $container): void
    {
        $this->process->preventRunningActualProcesses();

        $executor = $container->get(ProcessExecutor::class);

        test(fn () => $executor->run('echo nope'))
            ->exceptionThrown(ProcessExecutionWasForbidden::class);
    }

    #[Test]
    public function describes_started_processes(Container $container): void
    {
        $this->process->mockProcessResult(
            'php *',
            $this->process
                ->describe()
                ->pid(123)
                ->output(['one', 'two'])
                ->errorOutput('bad')
                ->exitCode(9),
        );

        $executor = $container->get(ProcessExecutor::class);
        $process = $executor->start('php script.php');
        $seen = [];
        $result = $process->wait(function (OutputChannel $type, string $buffer) use (&$seen): void {
            $seen[] = [$type, $buffer];
        });

        test($process->pid)->is(123);
        test($result->exitCode)->is(9);
        test($result->output)->is("one\ntwo\n");
        test($result->errorOutput)->is("bad\n");
        test($seen)->hasCount(3);

        $this->process->assertCommandRan('php *');
    }
}
