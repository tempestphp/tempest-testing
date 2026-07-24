<?php

namespace Tempest\Testing\Tests;

use Closure;
use ReflectionMethod;
use ReflectionProperty;
use Stringable;
use Symfony\Component\Process\Process;
use Tempest\Console\Console;
use Tempest\Console\ExitCode;
use Tempest\Console\InteractiveConsoleComponent;
use Tempest\Console\Output\MemoryOutputBuffer;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Core\Kernel;
use Tempest\EventBus\EventBus;
use Tempest\EventBus\EventBusConfig;
use Tempest\Http\GenericRequest;
use Tempest\Http\Request;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Testing\Actions\RunTest;
use Tempest\Testing\Config\TestConfig;
use Tempest\Testing\Console\TestCommand;
use Tempest\Testing\Console\WithDiscoveredTestsMiddleware;
use Tempest\Testing\Events\DispatchToParentProcessMiddleware;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Output\InteractiveOutput;
use Tempest\Testing\Output\TestOutput;
use Tempest\Testing\Runner\TestRunner;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;
use UnitEnum;

use function Tempest\Testing\test;

final class CliCommandBehaviorTest
{
    #[Test]
    public function test_command_registers_the_test_environment(): void
    {
        $container = $this->container();

        $this->withGlobalContainer(
            $container,
            fn () => $this->command($container, new TestConfig([]))->__invoke(
                verbose: true,
                failFast: true,
                debug: true,
                skipped: true,
                interaction: false,
            ),
        );

        $environment = $container->get(TestEnvironment::class);

        test($environment->verbose)->is(true);
        test($environment->debug)->is(true);
        test($environment->failFast)->is(true);
        test($environment->skipped)->is(true);
    }

    #[Test]
    public function test_command_uses_interactive_output_only_when_interaction_teamcity_and_tty_allow_it(): void
    {
        $container = $this->container();
        $console = new CapturingConsole();
        $command = $this->command($container, new TestConfig([]), supportsTty: fn (): bool => true, console: $console);

        $this->withGlobalContainer(
            $container,
            fn () => $command->__invoke(interaction: true),
        );

        test($console->component)->instanceOf(InteractiveOutput::class);
        test($container->get(TestOutput::class))->instanceOf(InteractiveOutput::class);

        $this->assertNoInteractiveOutput(interaction: false, teamcity: false, supportsTty: true);
        $this->assertNoInteractiveOutput(interaction: true, teamcity: false, supportsTty: false);
        $this->assertNoInteractiveOutput(interaction: true, teamcity: true, supportsTty: true);
    }

    #[Test]
    public function test_skipped_outputs_details_without_verbose(): void
    {
        $output = $this->runTempest([
            'test',
            'SkippedTest::skip1',
            '--no-interaction',
            '--skipped',
        ]);

        test($output)->contains('Tempest\Testing\Tests\SkippedTest::skip1');
        test($output)->contains('tests/SkippedTest.php');
    }

    #[Test]
    public function test_command_forwards_process_count_fail_fast_and_debug_to_runners(): void
    {
        $processOutput = $this->runTempest([
            'test',
            'TestMetadataTest',
            '--no-interaction',
            '--verbose',
            '--processes=2',
        ]);

        test($processOutput)->contains('will run on 2 processes');

        $debugOutput = $this->runTempest([
            'test',
            'TestMetadataTest::location_includes_the_declaring_file_and_start_line',
            '--no-interaction',
            '-d',
            '-f',
        ]);

        test($debugOutput)->contains('--debug');
        test($debugOutput)->contains('--fail-fast');
    }

    #[Test]
    public function filtering_tests_dispatches_skipped_events_and_returns_only_matching_tests(): void
    {
        $eventBus = new RecordingEventBus();
        $container = $this->container($eventBus);
        $matching = Test::fromReflector(new MethodReflector(new ReflectionMethod(CliCommandFixture::class, 'matching')));
        $excluded = Test::fromReflector(new MethodReflector(new ReflectionMethod(CliCommandFixture::class, 'excluded')));
        $command = $this->command($container, new TestConfig([$matching, $excluded]));
        $getTests = new ReflectionMethod($command, 'getTests');

        $tests = $this->withGlobalContainer(
            $container,
            fn () => $getTests->invoke($command, 'matching'),
        );

        if (! $tests instanceof ImmutableArray) {
            test()->fail('Filtered tests did not resolve to an immutable array.');
        }

        test($tests->count())->is(1);
        $filteredTests = array_values($tests->toArray());
        $filteredTest = $filteredTests[0] ?? null;

        if (! $filteredTest instanceof Test) {
            test()->fail('Filtered tests did not contain a test.');
        }

        test($filteredTest->name)->is($matching->name);
        test($eventBus->events)->hasCount(1);

        $event = $eventBus->events[0];

        if (! $event instanceof TestSkipped) {
            test()->fail('Filtered test did not dispatch a skipped event.');
        }

        test($event->name)->is($excluded->name);
    }

    #[Test]
    public function filter_dispatched_skipped_tests_without_location_do_not_print_skipped_details(): void
    {
        $output = $this->runTempest([
            'test',
            'PrimitiveTesterTest::succeed',
            '--no-interaction',
            '--skipped',
        ]);

        test($output)->containsNot('PrimitiveTesterTest::fail');
    }

    #[Test]
    public function test_run_boots_child_container_with_runner_services_request_and_middleware(Kernel $kernel): void
    {
        $logFile = $this->temporaryFile();
        $this->removeFile($logFile);

        try {
            $this->runTempest([
                'test:run',
                '--name=cli-child',
                '--tests=' . CliCommandFixture::class . '::childContainer',
            ], [
                'TEMPEST_CLI_COMMAND_LOG' => $logFile,
            ]);

            $log = $this->readLog($logFile);
        } finally {
            $this->removeFile($logFile);
        }

        test($log)->contains('run-test=yes');
        test($log)->contains('test-runner=cli-child');
        test($log)->contains('environment=yes');
        test($log)->contains('request=yes');
        test($log)->contains('generic-request=yes');
        test($log)->contains('middleware=yes');
        test($log)->contains('internal-storage=' . $kernel->root . '/.tempest/test_internal_storage/cli-child');
    }

    #[Test]
    public function with_discovered_tests_middleware_is_skipped_by_discovery(): void
    {
        test(new ClassReflector(WithDiscoveredTestsMiddleware::class)->hasAttribute(\Tempest\Discovery\SkipDiscovery::class))->is(true);
    }

    private function assertNoInteractiveOutput(bool $interaction, bool $teamcity, bool $supportsTty): void
    {
        $container = $this->container();
        $console = new CapturingConsole();

        $this->withGlobalContainer(
            $container,
            fn () => $this->command($container, new TestConfig([]), supportsTty: fn (): bool => $supportsTty, console: $console)->__invoke(
                teamcity: $teamcity,
                interaction: $interaction,
            ),
        );

        test($console->component)->is(null);
        test(fn () => $container->get(TestOutput::class))->exceptionThrown(\Throwable::class);
    }

    private function container(?RecordingEventBus $eventBus = null): GenericContainer
    {
        $container = new GenericContainer();
        $container->singleton(EventBus::class, $eventBus ?? new RecordingEventBus());

        return $container;
    }

    private function command(
        Container $container,
        TestConfig $config,
        ?Closure $supportsTty = null,
        ?Console $console = null,
    ): TestCommand {
        $command = new TestCommand($container, $config, $supportsTty);

        if ($console !== null) {
            $property = new ReflectionProperty($command, 'console');
            $property->setValue($command, $console);
        }

        return $command;
    }

    private function withGlobalContainer(GenericContainer $container, Closure $callback): mixed
    {
        $previous = GenericContainer::instance();
        GenericContainer::setInstance($container);

        try {
            return $callback();
        } finally {
            GenericContainer::setInstance($previous);
        }
    }

    /** @param string[] $arguments @param array<string, string> $env */
    private function runTempest(array $arguments, array $env = []): string
    {
        $process = new Process(
            [
                PHP_BINDIR . '/php',
                'tempest',
                ...$arguments,
            ],
            env: $env,
        );
        $process->setTimeout(20);
        $process->run();

        $output = $process->getOutput() . $process->getErrorOutput();

        if (! $process->isSuccessful()) {
            test()->fail('Tempest command failed: %s', $output);
        }

        return $output;
    }

    private function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tempest-cli-command-');

        if ($path === false) {
            test()->fail('Could not create temporary file.');
        }

        return $path;
    }

    /** @return string[] */
    private function readLog(string $logFile): array
    {
        if (! file_exists($logFile)) {
            return [];
        }

        return array_values(array_filter(explode(PHP_EOL, trim((string) file_get_contents($logFile)))));
    }

    private function removeFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

final class CliCommandFixture
{
    public function matching(): void {}

    public function excluded(): void {}

    public function childContainer(
        Container $container,
        Kernel $kernel,
        TestRunner $testRunner,
    ): void {
        $config = $container->get(EventBusConfig::class);
        $middleware = iterator_to_array($config->middleware->unwrap());

        $this->log('test-runner=' . $testRunner->name);
        $container->get(RunTest::class);
        $container->get(TestEnvironment::class);
        $container->get(Request::class);
        $container->get(GenericRequest::class);

        $this->log('run-test=yes');
        $this->log('environment=yes');
        $this->log('request=yes');
        $this->log('generic-request=yes');
        $this->log('middleware=' . (array_key_exists(DispatchToParentProcessMiddleware::class, $middleware) ? 'yes' : 'no'));
        $this->log('internal-storage=' . $kernel->internalStorage);
    }

    private function log(string $line): void
    {
        $logFile = getenv('TEMPEST_CLI_COMMAND_LOG');

        if (! is_string($logFile) || $logFile === '') {
            return;
        }

        file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    }
}

final class RecordingEventBus implements EventBus
{
    /** @var array<int, object> */
    public array $events = [];

    public function dispatch(object|string $event): void
    {
        if (is_object($event)) {
            $this->events[] = $event;
        }
    }

    public function listen(Closure $handler, string|UnitEnum|null $event = null): void
    {
        unset($handler, $event);
    }
}

final class CapturingConsole implements Console
{
    public ?InteractiveConsoleComponent $component = null;

    public function call(string|array $command, string|array $arguments = []): ExitCode|int
    {
        unset($command, $arguments);

        return ExitCode::SUCCESS;
    }

    public function readln(): string
    {
        return '';
    }

    public function read(int $bytes): string
    {
        unset($bytes);

        return '';
    }

    public function write(string $contents): self
    {
        unset($contents);

        return $this;
    }

    public function writeln(string $line = ''): self
    {
        unset($line);

        return $this;
    }

    public function writeRaw(string $contents): self
    {
        unset($contents);

        return $this;
    }

    public function writeWithLanguage(string $contents, \Tempest\Highlight\Language $language): self
    {
        unset($contents, $language);

        return $this;
    }

    public function component(InteractiveConsoleComponent $component, array $validation = []): mixed
    {
        unset($validation);

        $this->component = $component;

        return null;
    }

    public function ask(
        string $question,
        iterable|string|null $options = null,
        mixed $default = null,
        bool $multiple = false,
        bool $multiline = false,
        ?string $placeholder = null,
        ?string $hint = null,
        array $validation = [],
    ): int|string|Stringable|UnitEnum|array|null {
        unset($question, $options, $multiple, $multiline, $placeholder, $hint, $validation);

        if ($default === null || is_int($default) || is_string($default) || $default instanceof Stringable || $default instanceof UnitEnum || is_array($default)) {
            return $default;
        }

        return null;
    }

    public function confirm(string $question, bool $default = false, ?string $yes = null, ?string $no = null): bool
    {
        unset($question, $yes, $no);

        return $default;
    }

    public function password(string $label = 'Password', bool $confirm = false, array $validation = []): ?string
    {
        unset($label, $confirm, $validation);

        return null;
    }

    public function progressBar(iterable $data, Closure $handler): array
    {
        return array_map($handler, is_array($data) ? $data : iterator_to_array($data));
    }

    public function search(string $label, Closure $search, bool $multiple = false, string|array|null $default = null): mixed
    {
        unset($label, $search, $multiple);

        return $default;
    }

    public function task(string $label, \Symfony\Component\Process\Process|Closure|null $handler): bool
    {
        unset($label);

        if ($handler instanceof Closure) {
            $handler();
        }

        return true;
    }

    public function header(string $header, ?string $subheader = null): self
    {
        unset($header, $subheader);

        return $this;
    }

    public function info(string $contents, ?string $title = null): self
    {
        unset($contents, $title);

        return $this;
    }

    public function error(string $contents, ?string $title = null): self
    {
        unset($contents, $title);

        return $this;
    }

    public function warning(string $contents, ?string $title = null): self
    {
        unset($contents, $title);

        return $this;
    }

    public function success(string $contents, ?string $title = null): self
    {
        unset($contents, $title);

        return $this;
    }

    public function keyValue(string $key, ?string $value = null, bool $useAvailableWidth = false): self
    {
        unset($key, $value, $useAvailableWidth);

        return $this;
    }

    public function instructions(array|string $lines): self
    {
        unset($lines);

        return $this;
    }

    public function when(mixed $condition, Closure $callback): self
    {
        if ($condition === true || $condition instanceof Closure && $condition($this) === true) {
            $console = $callback($this);

            return $console instanceof self ? $console : $this;
        }

        return $this;
    }

    public function unless(mixed $condition, Closure $callback): self
    {
        if ($condition === false || $condition instanceof Closure && $condition($this) === false) {
            $console = $callback($this);

            return $console instanceof self ? $console : $this;
        }

        return $this;
    }

    public function withLabel(string $label): self
    {
        unset($label);

        return $this;
    }

    public function supportsPrompting(): bool
    {
        return false;
    }

    public function disablePrompting(): self
    {
        return $this;
    }

    public bool $isForced {
        get => false;
    }

    public MemoryOutputBuffer $output {
        get => new MemoryOutputBuffer();
    }
}
