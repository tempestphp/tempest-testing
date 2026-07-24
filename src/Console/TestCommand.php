<?php

namespace Tempest\Testing\Console;

use Closure;
use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;
use Tempest\Console\Terminal\Terminal;
use Tempest\Container\Container;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Testing\Actions\ChunkAndRunTests;
use Tempest\Testing\Config\TestConfig;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Output\InteractiveOutput;
use Tempest\Testing\Output\TestOutput;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;

use function Tempest\EventBus\event;
use function Tempest\Support\arr;

final class TestCommand
{
    use HasConsole;

    public function __construct(
        private readonly Container $container,
        private readonly TestConfig $testConfig,
        private readonly ?Closure $supportsTty = null,
    ) {}

    #[ConsoleCommand(
        aliases: ['gust'],
        middleware: [WithDiscoveredTestsMiddleware::class],
    )]
    public function __invoke(
        #[ConsoleArgument(description: 'Only run tests matching this fuzzy filter')]
        ?string $filter = null,
        #[ConsoleArgument(description: 'Number of processes to run tests in parallel', aliases: ['-p'])]
        int $processes = 5,
        #[ConsoleArgument(description: 'Show all output, including succeeding and skipped tests', aliases: ['-v'])]
        bool $verbose = false,
        #[ConsoleArgument(description: 'Fail as soon as an error occurs', aliases: ['-f'])]
        bool $failFast = false,
        #[ConsoleArgument(description: 'Show debug output', aliases: ['-d'])]
        bool $debug = false,
        #[ConsoleArgument(description: 'Show skipped tests')]
        bool $skipped = false,
        #[ConsoleArgument(description: 'Show slow tests')]
        bool $slow = false,
        #[ConsoleArgument(description: 'Use teamcity output format')]
        bool $teamcity = false,
        #[ConsoleArgument(description: 'Show interactive output', aliases: ['-i'])]
        bool $interaction = true,
    ): void {
        $testEnvironment = new TestEnvironment(
            verbose: $verbose,
            debug: $debug,
            failFast: $failFast,
            skipped: $skipped,
            slow: $slow,
        );

        $this->container->singleton(TestEnvironment::class, $testEnvironment);

        $shouldBeInteractive = ! $debug && ! $teamcity && $interaction && ($this->supportsTty ?? Terminal::supportsTty(...))();

        if ($shouldBeInteractive) {
            $output = new InteractiveOutput(
                fn (InteractiveOutput $output) => new ChunkAndRunTests(
                    testEnvironment: $testEnvironment,
                    outputHandler: $output->appendProcessOutput(...),
                )->runWithUpdates(
                    tests: $this->getTests($filter),
                    processes: $processes,
                ),
            );
            $output->testEnvironment = $testEnvironment;

            $this->container->singleton(TestOutput::class, $output);
            $this->console->component($output);
        } else {
            (new ChunkAndRunTests($testEnvironment))(
                tests: $this->getTests($filter),
                processes: $processes,
            );
        }
    }

    private function getTests(?string $filter): ImmutableArray
    {
        $tests = arr($this->testConfig->tests);

        if (! $filter) {
            return $tests;
        }

        return $tests->filter(function (Test $test) use ($filter) {
            if (! $test->matchesFilter($filter)) {
                event(new TestSkipped($test->name));
                return false;
            }

            return true;
        });
    }
}
