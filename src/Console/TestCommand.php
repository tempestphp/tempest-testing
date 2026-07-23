<?php

namespace Tempest\Testing\Console;

use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;
use Tempest\Core\Environment;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Testing\Actions\ChunkAndRunTests;
use Tempest\Testing\Config\TestConfig;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Test;

use function Tempest\Container\get;
use function Tempest\EventBus\event;
use function Tempest\Support\arr;

final class TestCommand
{
    use HasConsole;

    public function __construct(
        private readonly TestConfig $testConfig,
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
        #[ConsoleArgument(description: 'Show debug output', aliases: ['-d'])]
        bool $debug = false,
        #[ConsoleArgument(description: 'Use teamcity output format')]
        bool $teamcity = false,
    ): void {
        new ChunkAndRunTests(
            debug: $debug,
        )(
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
