<?php

namespace Tempest\Testing\Tests;

use ReflectionMethod;
use RuntimeException;
use Tempest\Container\Container;
use Tempest\Reflection\MethodReflector;
use Tempest\Testing\Actions\RunTest;
use Tempest\Testing\After;
use Tempest\Testing\Events\TestFailed;
use Tempest\Testing\Events\TestFinished;
use Tempest\Testing\Events\TestSucceeded;
use Tempest\Testing\Provide;
use Tempest\Testing\Test;
use Tempest\Testing\Testers\EventBus\TestsEvents;

use function Tempest\Testing\test;

final class FailingTest
{
    use TestsEvents;

    #[Test]
    public function assertion_failures_return_false_run_after_hooks_and_dispatch_failure(Container $container): void
    {
        FailingFixture::reset();
        $this->events->preventPropagation();

        $test = $this->testFor('assertionFailure');
        $passed = (new RunTest($container))($test);

        test($passed)->is(false);
        test(FailingFixture::$afterRuns)->is(1);

        $this->events->wasDispatched(TestFailed::class, function (TestFailed $event) use ($test): void {
            test($event->name)->is($test->name);
            test($event->reason)->is('assertion failure');
            test($event->location)->is(__FILE__ . ':' . FailingFixture::$assertionFailureLine);
            test($event->trace)->is(null);
        });
        $this->events->wasDispatched(TestFinished::class, function (TestFinished $event) use ($test): void {
            test($event->name)->is($test->name);
            test($event->duration)->greaterThanOrEqual(0);
        });
        $this->events->wasNotDispatched(TestSucceeded::class);
    }

    #[Test]
    public function thrown_exceptions_return_false_run_after_hooks_and_dispatch_failure(Container $container): void
    {
        FailingFixture::reset();
        $this->events->preventPropagation();

        $test = $this->testFor('throwsException');
        $passed = (new RunTest($container))($test);

        test($passed)->is(false);
        test(FailingFixture::$afterRuns)->is(1);

        $this->events->wasDispatched(TestFailed::class, function (TestFailed $event) use ($test): void {
            test($event->name)->is($test->name);
            test($event->reason)->is('unexpected exception');
            test($event->location)->is($test->location);
            test($event->trace)->contains('throwsException');
        });
        $this->events->wasDispatched(TestFinished::class, function (TestFinished $event) use ($test): void {
            test($event->name)->is($test->name);
            test($event->duration)->greaterThanOrEqual(0);
        });
        $this->events->wasNotDispatched(TestSucceeded::class);
    }

    #[Test]
    public function provider_tests_stop_after_the_first_failing_entry(Container $container): void
    {
        FailingFixture::reset();
        $this->events->preventPropagation();

        $passed = (new RunTest($container))($this->testFor('failingProviderEntry'));

        test($passed)->is(false);
        test(FailingFixture::$afterRuns)->is(1);
        test(FailingFixture::$providerRuns)->is(['fail']);
        $this->events->wasDispatched(TestFailed::class);
        $this->events->wasNotDispatched(TestSucceeded::class);
    }

    #[Test]
    public function test_failed_serializes_and_deserializes_trace(): void
    {
        $event = new TestFailed(
            name: 'Tests\ExampleTest::it_fails',
            reason: 'failure reason',
            location: '/tests/ExampleTest.php:10',
            trace: 'trace output',
        );

        $deserialized = TestFailed::deserialize($event->serialize());

        if (! $deserialized instanceof TestFailed) {
            test()->fail('Expected deserialized event to be a TestFailed instance.');
        }

        test($deserialized->name)->is($event->name);
        test($deserialized->reason)->is($event->reason);
        test($deserialized->location)->is($event->location);
        test($deserialized->trace)->is($event->trace);
    }

    #[Test]
    public function test_finished_serializes_and_deserializes_duration(): void
    {
        $event = new TestFinished(
            name: 'Tests\ExampleTest::it_finishes',
            location: '/tests/ExampleTest.php:10',
            duration: 12.34,
        );

        $deserialized = TestFinished::deserialize($event->serialize());

        if (! $deserialized instanceof TestFinished) {
            test()->fail('Expected deserialized event to be a TestFinished instance.');
        }

        test($deserialized->name)->is($event->name);
        test($deserialized->duration)->is($event->duration);
    }

    #[Test]
    public function failed_events_created_from_throwables_use_the_test_location(): void
    {
        $test = $this->testFor('throwsException');
        $event = null;

        try {
            new FailingFixture()->throwsException();
        } catch (RuntimeException $throwable) {
            $event = TestFailed::fromThrowable($test, $throwable);
        }

        if (! $event instanceof TestFailed) {
            test()->fail('Expected throwable to create a TestFailed event.');
        }

        test($event->location)->is($test->location);
        test($event->reason)->is('unexpected exception');
        test($event->trace)->contains('throwsException');
    }

    private function testFor(string $method): Test
    {
        return Test::fromReflector(new MethodReflector(new ReflectionMethod(FailingFixture::class, $method)));
    }
}

final class FailingFixture
{
    public static int $afterRuns = 0;

    public static int $assertionFailureLine = 0;

    /** @var string[] */
    public static array $providerRuns = [];

    public static function reset(): void
    {
        self::$afterRuns = 0;
        self::$assertionFailureLine = 0;
        self::$providerRuns = [];
    }

    #[After]
    public function after(): void
    {
        self::$afterRuns++;
    }

    public function assertionFailure(): void
    {
        self::$assertionFailureLine = __LINE__ + 1;
        test()->fail('assertion failure');
    }

    public function throwsException(): void
    {
        throw new RuntimeException('unexpected exception');
    }

    #[
        Provide(
            ['value' => 'fail'],
            ['value' => 'pass'],
        ),
    ]
    public function failingProviderEntry(string $value): void
    {
        self::$providerRuns[] = $value;

        if ($value === 'fail') {
            test()->fail('provider failure');
        }

        test()->succeed();
    }
}
