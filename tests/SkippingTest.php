<?php

namespace Tempest\Testing\Tests;

use ReflectionMethod;
use Tempest\Container\Container;
use Tempest\EventBus\EventBusMiddlewareCallable;
use Tempest\Reflection\MethodReflector;
use Tempest\Testing\Actions\RunTest;
use Tempest\Testing\After;
use Tempest\Testing\Events\DispatchToParentProcessMiddleware;
use Tempest\Testing\Events\TestFinished;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Events\TestSucceeded;
use Tempest\Testing\Exceptions\TestWasSkipped;
use Tempest\Testing\Output\TeamcityOutput;
use Tempest\Testing\Provide;
use Tempest\Testing\Test;
use Tempest\Testing\Testers\Console\TestsConsole;
use Tempest\Testing\Testers\EventBus\TestsEvents;

use function Tempest\Testing\test;

final class SkippingTest
{
    use TestsConsole;
    use TestsEvents;

    #[Test]
    public function primitive_tester_can_skip_with_a_reason(): void
    {
        test(fn () => test()->skip('not implemented yet'))
            ->exceptionThrown(TestWasSkipped::class, function (TestWasSkipped $exception): void {
                test($exception->reason)->is('not implemented yet');
            });
    }

    #[Test]
    public function run_test_dispatches_skipped_events_and_still_passes(Container $container): void
    {
        SkippingFixture::reset();
        $this->events->preventPropagation();

        $test = $this->testFor('skippedTest');
        $passed = (new RunTest($container))($test);

        test($passed)->is(true);
        test(SkippingFixture::$afterRuns)->is(1);

        $this->events->wasDispatched(TestSkipped::class, function (TestSkipped $event) use ($test): void {
            test($event->name)->is($test->name);
            test($event->reason)->is('skip reason');
            test($event->location)->is($test->location);
        });
        $this->events->wasDispatched(TestFinished::class, function (TestFinished $event) use ($test): void {
            test($event->name)->is($test->name);
        });
        $this->events->wasNotDispatched(TestSucceeded::class);
    }

    #[Test]
    public function skipped_provider_entries_do_not_fail_the_test(Container $container): void
    {
        SkippingFixture::reset();
        $this->events->preventPropagation();

        $passed = (new RunTest($container))($this->testFor('skippedProviderEntry'));

        test($passed)->is(true);
        test(SkippingFixture::$afterRuns)->is(2);
        $this->events->wasDispatched(TestSkipped::class);
        $this->events->wasDispatched(TestSucceeded::class);
    }

    #[Test]
    public function test_skipped_serializes_and_deserializes_payload(): void
    {
        $event = new TestSkipped(
            name: 'Tests\ExampleTest::it_skips',
            reason: 'skip reason',
            location: '/tests/ExampleTest.php:10',
        );

        $deserialized = TestSkipped::deserialize($event->serialize());

        if (! $deserialized instanceof TestSkipped) {
            test()->fail('Expected deserialized event to be a TestSkipped instance.');
        }

        test($deserialized->name)->is($event->name);
        test($deserialized->reason)->is($event->reason);
        test($deserialized->location)->is($event->location);
    }

    #[Test]
    public function test_skipped_is_written_for_the_parent_process(Container $container): void
    {
        $this->console
            ->call(function () use ($container): void {
                $middleware = $container->get(DispatchToParentProcessMiddleware::class);

                $middleware(
                    new TestSkipped(
                        name: 'Tests\ExampleTest::it_skips',
                        reason: 'skip reason',
                        location: '/tests/ExampleTest.php:10',
                    ),
                    new EventBusMiddlewareCallable(fn (): null => null),
                );
            })
            ->contains(
                '[EVENT] {"event":"Tempest\\\\Testing\\\\Events\\\\TestSkipped","data":{"name":"Tests\\\\ExampleTest::it_skips","reason":"skip reason","location":"\\/tests\\/ExampleTest.php:10"}}',
            );
    }

    #[Test]
    public function teamcity_output_writes_skipped_tests(Container $container): void
    {
        $this->console
            ->call(function () use ($container): void {
                $container
                    ->get(TeamcityOutput::class)
                    ->onTestSkipped(new TestSkipped('Tests\ExampleTest::it_skips'));
            })
            ->contains("##teamcity[testIgnored name='Tests\ExampleTest::it_skips']");
    }

    private function testFor(string $method): Test
    {
        return Test::fromReflector(new MethodReflector(new ReflectionMethod(SkippingFixture::class, $method)));
    }
}

final class SkippingFixture
{
    public static int $afterRuns = 0;

    public static function reset(): void
    {
        self::$afterRuns = 0;
    }

    #[After]
    public function after(): void
    {
        self::$afterRuns++;
    }

    public function skippedTest(): void
    {
        test()->skip('skip reason');
    }

    #[
        Provide(
            ['value' => 'skip'],
            ['value' => 'pass'],
        ),
    ]
    public function skippedProviderEntry(string $value): void
    {
        if ($value === 'skip') {
            test()->skip('provider skip');
        }

        test()->succeed();
    }
}
