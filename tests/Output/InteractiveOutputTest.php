<?php

namespace Tempest\Testing\Tests\Output;

use ReflectionClass;
use Tempest\Console\Terminal\Terminal;
use Tempest\Testing\Events\TestRunEnded;
use Tempest\Testing\Events\TestRunStarted;
use Tempest\Testing\Events\TestSkipped;
use Tempest\Testing\Output\InteractiveOutput;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;

use function Tempest\Testing\test;

final class InteractiveOutputTest
{
    #[Test]
    public function skipped_output_is_rendered_as_soon_as_it_is_reported(): void
    {
        $output = new InteractiveOutput(function (InteractiveOutput $output) {
            $output->onTestRunStarted(new TestRunStarted());
            $output->onTestSkipped(new TestSkipped(
                name: 'Tests\ExampleTest::first',
                reason: 'first reason',
                location: '/tests/ExampleTest.php:10',
            ));

            yield;

            yield;

            $output->onTestSkipped(new TestSkipped(
                name: 'Tests\ExampleTest::second',
                reason: 'second reason',
                location: '/tests/ExampleTest.php:20',
            ));

            yield;

            $output->onTestRunEnded(new TestRunEnded());
        });
        $output->testEnvironment = new TestEnvironment(skipped: true);

        $terminal = new ReflectionClass(Terminal::class)->newInstanceWithoutConstructor();
        $frames = iterator_to_array($output->render($terminal));

        test($frames[0])->is('');
        test($frames[1])->contains('Tests\ExampleTest::first');
        test($frames[1])->containsNot('Tests\ExampleTest::second');
        test($frames[2])->is($frames[1]);
        test($frames[3])->containsNot('Tests\ExampleTest::first');
        test($frames[3])->contains('Tests\ExampleTest::second');
        test($frames[4])->contains('Tests\ExampleTest::first');
        test($frames[4])->contains('Tests\ExampleTest::second');
        test(substr_count($frames[4], 'Tests\ExampleTest::'))->is(2);
    }
}
