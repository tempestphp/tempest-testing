<?php

namespace Tempest\Testing\Console;

use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;
use Tempest\Container\Container;
use Tempest\Core\Kernel\LoadDiscoveryClasses;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\EventBus\EventBus;
use Tempest\EventBus\EventBusDiscovery;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Testing\Actions\ChunkAndRunTests;
use Tempest\Testing\Config\TestConfig;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Output\DefaultOutput;
use Tempest\Testing\Output\TeamcityOutput;
use Tempest\Testing\Runner\TestResult;
use Tempest\Testing\Test;
use Tempest\Testing\TestOutput;
use function Tempest\event;
use function Tempest\Support\arr;

final class TestCommand
{
    use HasConsole;

    private bool $verbose = false;
    private TestResult $result;

    public function __construct(
        private readonly TestConfig $testConfig,
        private readonly Container $container,
    ) {}

    #[ConsoleCommand(
        aliases: ['gust'],
        middleware: [WithDiscoveredTestsMiddleware::class],
    )]
    public function __invoke(
        #[ConsoleArgument(description: 'Only run tests matching this fuzzy filter')]
        ?string $filter = null,
        #[ConsoleArgument(description: 'Number of processes to run tests in parallel')]
        int $processes = 5,
        #[ConsoleArgument(description: 'Show all output, including succeeding and skipped tests', aliases: ['-v'])]
        bool $verbose = false,
        #[ConsoleArgument(description: 'Use teamcity output format')]
        bool $teamcity = false,
    ): void
    {
        if ($teamcity) {
            $output = $this->container->get(TeamCityOutput::class);
        } else {
            $output = $this->container->get(DefaultOutput::class);
        }

        $output->verbose = $verbose;

        $this->container->singleton(TestOutput::class, $output);

        new ChunkAndRunTests()(
            tests: $this->getTests($filter),
            processes: $processes,
        );
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