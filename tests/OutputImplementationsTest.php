<?php

namespace Tempest\Testing\Tests;

use Closure;
use Generator;
use ReflectionClass;
use ReflectionMethod;
use Tempest\Console\ConsoleApplication;
use Tempest\Console\Input\ConsoleArgumentBag;
use Tempest\Console\Terminal\Terminal;
use Tempest\Container\Container;
use Tempest\Core\Application;
use Tempest\Testing\Events\TestFailed;
use Tempest\Testing\Events\TestFinished;
use Tempest\Testing\Events\TestRunEnded;
use Tempest\Testing\Events\TestRunStarted;
use Tempest\Testing\Events\TestsChunked;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Events\TestStarted;
use Tempest\Testing\Events\TestSucceeded;
use Tempest\Testing\Output\DefaultOutput;
use Tempest\Testing\Output\InteractiveOutput;
use Tempest\Testing\Output\TeamcityOutput;
use Tempest\Testing\Output\TestOutput;
use Tempest\Testing\Output\TestOutputInitializer;
use Tempest\Testing\Runner\TestResult;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;
use Tempest\Testing\Testers\Console\TestsConsole;

use function Tempest\Testing\test;

final class OutputImplementationsTest
{
    use TestsConsole;

    #[Test]
    public function output_initializer_injects_environment_into_default_output(Container $container): void
    {
        $environment = new TestEnvironment(verbose: true);
        $container->singleton(Application::class, $container->get(ConsoleApplication::class));
        $container->singleton(ConsoleArgumentBag::class, new ConsoleArgumentBag(['tempest', 'test']));
        $container->singleton(TestEnvironment::class, $environment);

        $output = new TestOutputInitializer()->initialize($container);

        if (! $output instanceof DefaultOutput) {
            test()->fail('Output was not a DefaultOutput.');
        }

        test($output->testEnvironment)->is($environment);
    }

    #[Test]
    public function output_initializer_injects_environment_into_teamcity_output(Container $container): void
    {
        $environment = new TestEnvironment(debug: true);
        $container->singleton(Application::class, $container->get(ConsoleApplication::class));
        $container->singleton(ConsoleArgumentBag::class, new ConsoleArgumentBag(['tempest', 'test', '--teamcity']));
        $container->singleton(TestEnvironment::class, $environment);

        $output = new TestOutputInitializer()->initialize($container);

        if (! $output instanceof TeamcityOutput) {
            test()->fail('Output was not a TeamcityOutput.');
        }

        test($output->testEnvironment)->is($environment);
    }

    #[Test]
    public function default_output_prints_chunk_count_only_in_verbose_mode(Container $container): void
    {
        $this->console->call(function () use ($container): void {
            $output = $this->defaultOutput($container, new TestEnvironment());
            $output->onTestsChunked(new TestsChunked(3));
        })->containsNot('will run on 3 processes');

        $this->console->call(function () use ($container): void {
            $output = $this->defaultOutput($container, new TestEnvironment(verbose: true));
            $output->onTestsChunked(new TestsChunked(3));
        })->contains('will run on 3 processes');
    }

    #[Test]
    public function default_output_prints_failures_and_traces_only_in_verbose_mode(Container $container): void
    {
        $failure = new TestFailed(
            name: 'Tests\ExampleTest::it_fails',
            reason: 'failed hard',
            location: '/tests/ExampleTest.php:10',
            trace: 'trace line',
        );

        $this->console
            ->call(function () use ($container, $failure): void {
                $output = $this->defaultOutput($container, new TestEnvironment());
                $output->onTestFailed($failure);
            })
            ->contains('Tests\ExampleTest::it_fails')
            ->contains('/tests/ExampleTest.php:10')
            ->contains('failed hard')
            ->containsNot('trace line');

        $this->console->call(function () use ($container, $failure): void {
            $output = $this->defaultOutput($container, new TestEnvironment(verbose: true));
            $output->onTestFailed($failure);
        })->contains('trace line');
    }

    #[Test]
    public function default_output_counts_skipped_tests_but_hides_details_by_default(Container $container): void
    {
        $this->console
            ->call(function () use ($container): void {
                $output = $this->defaultOutput($container, new TestEnvironment());
                $output->onTestRunStarted(new TestRunStarted());
                $output->onTestSkipped(new TestSkipped(
                    name: 'Tests\ExampleTest::it_skips',
                    reason: 'skip reason',
                    location: '/tests/ExampleTest.php:20',
                ));
                $output->onTestRunEnded(new TestRunEnded());
            })
            ->containsNot('Tests\ExampleTest::it_skips')
            ->contains('0 succeeded')
            ->contains('0 failed')
            ->contains('1 skipped');
    }

    #[Test]
    public function default_output_shows_skipped_details_when_enabled(Container $container): void
    {
        foreach ([
            new TestEnvironment(skipped: true),
            new TestEnvironment(verbose: true),
            new TestEnvironment(debug: true),
        ] as $environment) {
            $this->console
                ->call(function () use ($container, $environment): void {
                    $output = $this->defaultOutput($container, $environment);
                    $output->onTestSkipped(new TestSkipped(
                        name: 'Tests\ExampleTest::it_skips',
                        reason: 'skip reason',
                        location: '/tests/ExampleTest.php:20',
                    ));
                })
                ->contains('Tests\ExampleTest::it_skips')
                ->contains('/tests/ExampleTest.php:20')
                ->contains('skip reason');
        }
    }

    #[Test]
    public function default_output_summary_contains_counts_and_elapsed_time(Container $container): void
    {
        $this->console
            ->call(function () use ($container): void {
                $output = $this->defaultOutput($container, new TestEnvironment());
                $output->onTestRunStarted(new TestRunStarted());
                $output->onTestSucceeded(new TestSucceeded('pass'));
                $output->onTestFailed(new TestFailed('fail', 'reason', 'file.php:1'));
                $output->onTestSkipped(new TestSkipped('skip'));
                $output->onTestRunEnded(new TestRunEnded());
            })
            ->contains('1 succeeded')
            ->contains('1 failed')
            ->contains('1 skipped')
            ->contains('s');
    }

    #[Test]
    public function interactive_output_reports_current_test_and_clears_it(): void
    {
        $output = $this->interactiveOutput();
        $terminal = $this->terminal(height: 10);

        $output->onTestStarted(new TestStarted('Tests\ExampleTest::it_runs'));
        test($this->renderLiveBody($output, $terminal))->contains('Tests\ExampleTest::it_runs');

        $output->onTestFinished(new TestFinished('Tests\ExampleTest::it_runs', 'file.php:1'));
        test($this->renderLiveBody($output, $terminal))->is('');

        $output->onTestStarted(new TestStarted('Tests\ExampleTest::it_runs_again'));
        $output->onTestRunEnded(new TestRunEnded());
        test($this->renderLiveBody($output, $terminal))->is('');
    }

    #[Test]
    public function interactive_output_counts_and_appends_process_output(): void
    {
        $output = $this->interactiveOutput();
        $terminal = $this->terminal(height: 20);

        $output->onTestSucceeded(new TestSucceeded('pass'));
        $output->onTestFailed(new TestFailed('fail', 'reason', 'file.php:1'));
        $output->onTestSkipped(new TestSkipped('skip', location: 'file.php:2'));
        $output->appendProcessOutput('raw process output');

        test($output->renderFooter($terminal))->contains('1 succeeded');
        test($output->renderFooter($terminal))->contains('1 failed');
        test($output->renderFooter($terminal))->contains('1 skipped');
        test($this->renderLiveBody($output, $terminal))->contains('raw process output');
    }

    #[Test]
    public function interactive_output_shows_slow_tests_when_enabled(): void
    {
        $output = $this->interactiveOutput(new TestEnvironment(slow: true, slowThreshold: 50.0));
        $terminal = $this->terminal(height: 20);

        $output->onTestStarted(new TestStarted('Tests\ExampleTest::it_is_slow'));
        $output->onTestFinished(new TestFinished('Tests\ExampleTest::it_is_slow', 'file.php:1', duration: 75.5));
        $output->onTestFinished(new TestFinished('Tests\ExampleTest::it_is_fast', 'file.php:2', duration: 10.0));

        test($this->renderLiveBody($output, $terminal))
            ->contains('Tests\ExampleTest::it_is_slow')
            ->contains('Took 75.5ms')
            ->contains('file.php:1')
            ->containsNot('Tests\ExampleTest::it_is_fast');
        test($output->renderFooter($terminal))->contains('1 slow');
    }

    #[Test]
    public function interactive_output_shows_failures_and_verbose_traces(): void
    {
        $failure = new TestFailed('fail', 'reason', 'file.php:1', 'trace line');
        $terminal = $this->terminal(height: 20);

        $output = $this->interactiveOutput(new TestEnvironment());
        $output->onTestFailed($failure);

        test($this->renderLiveBody($output, $terminal))
            ->contains('fail')
            ->contains('file.php:1')
            ->contains('reason')
            ->containsNot('trace line');

        $verboseOutput = $this->interactiveOutput(new TestEnvironment(verbose: true));
        $verboseOutput->onTestFailed($failure);

        test($this->renderLiveBody($verboseOutput, $terminal))->contains('trace line');
    }

    #[Test]
    public function interactive_output_shows_and_hides_skipped_details(): void
    {
        $terminal = $this->terminal(height: 20);
        $hiddenOutput = $this->interactiveOutput();

        $hiddenOutput->onTestSkipped(new TestSkipped(
            name: 'skip',
            reason: 'skip reason',
            location: 'file.php:2',
        ));

        test($this->renderLiveBody($hiddenOutput, $terminal))->containsNot('skip');
        test($hiddenOutput->renderFooter($terminal))->contains('1 skipped');

        foreach ([
            new TestEnvironment(skipped: true),
            new TestEnvironment(verbose: true),
            new TestEnvironment(debug: true),
        ] as $environment) {
            $output = $this->interactiveOutput($environment);
            $output->onTestSkipped(new TestSkipped('skip', 'skip reason', 'file.php:2'));

            test($this->renderLiveBody($output, $terminal))
                ->contains('skip')
                ->contains('file.php:2')
                ->contains('skip reason');
        }
    }

    #[Test]
    public function interactive_output_does_not_duplicate_body_on_spinner_ticks_and_final_frame_is_complete_once(): void
    {
        $output = $this->interactiveOutput();
        $terminal = $this->terminal(height: 20);
        $output->onTestFailed(new TestFailed('fail', 'reason', 'file.php:1'));

        $firstTick = $this->renderLiveBody($output, $terminal);
        $secondTick = $this->renderLiveBody($output, $terminal);
        $final = $this->renderFinalBody($output, $terminal);

        test($secondTick)->is($firstTick);
        test(substr_count($final, 'file.php:1'))->is(1);
        test($final)->contains('0 succeeded');
        test($final)->contains('1 failed');
    }

    #[Test]
    public function interactive_output_render_returns_false_when_a_test_failed(): void
    {
        $output = $this->interactiveOutput(
            runner: function (InteractiveOutput $output): Generator {
                $output->onTestFailed(new TestFailed('fail', 'reason', 'file.php:1'));

                yield;
            },
        );

        $generator = $output->render($this->terminal(height: 20));

        foreach ($generator as $_) {
            unset($_);
        }

        test($generator->getReturn())->is(false);
    }

    #[Test]
    public function teamcity_output_uses_the_test_output_interface(Container $container): void
    {
        $this->console
            ->call(function () use ($container): void {
                $output = $container->get(TeamcityOutput::class);
                $output->testEnvironment = new TestEnvironment(verbose: true);

                test($output)->instanceOf(TestOutput::class);

                $output->onTestRunStarted(new TestRunStarted());
                $output->onTestStarted(new TestStarted('Tests\ExampleTest::it_runs'));
                $output->onTestFinished(new TestFinished('Tests\ExampleTest::it_runs', 'file.php:1'));
                $output->onTestRunEnded(new TestRunEnded());
            })
            ->contains('##teamcity[testSuiteStarted')
            ->contains('##teamcity[testStarted')
            ->contains('##teamcity[testFinished')
            ->contains('##teamcity[testSuiteFinished');
    }

    #[Test]
    public function test_result_elapsed_time_starts_advances_and_freezes(): void
    {
        $result = new TestResult();

        test($result->elapsedTime)->is(0.0);

        $result->startTime();
        usleep(10_000);
        $running = $result->elapsedTime;

        test($running)->greaterThan(0);

        $result->endTime();
        $finished = $result->elapsedTime;
        usleep(10_000);

        test($result->elapsedTime)->is($finished);
    }

    private function defaultOutput(Container $container, TestEnvironment $environment): DefaultOutput
    {
        $output = $container->get(DefaultOutput::class);
        $output->testEnvironment = $environment;

        return $output;
    }

    /** @param null|Closure(InteractiveOutput): iterable $runner */
    private function interactiveOutput(
        ?TestEnvironment $environment = null,
        ?Closure $runner = null,
    ): InteractiveOutput {
        $output = new InteractiveOutput($runner ?? fn (InteractiveOutput $output): array => []);
        $output->testEnvironment = $environment ?? new TestEnvironment();

        return $output;
    }

    private function terminal(int $height): Terminal
    {
        $terminal = new ReflectionClass(Terminal::class)->newInstanceWithoutConstructor();
        $terminal->height = $height;

        return $terminal;
    }

    private function renderLiveBody(InteractiveOutput $output, Terminal $terminal): string
    {
        $method = new ReflectionMethod($output, 'renderLiveBody');
        $body = $method->invoke($output, $terminal);

        if (! is_string($body)) {
            test()->fail('Live body did not render to a string.');
        }

        return $body;
    }

    private function renderFinalBody(InteractiveOutput $output, Terminal $terminal): string
    {
        $method = new ReflectionMethod($output, 'renderFinalBody');
        $body = $method->invoke($output, $terminal);

        if (! is_string($body)) {
            test()->fail('Final body did not render to a string.');
        }

        return $body;
    }
}
