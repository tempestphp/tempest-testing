<?php

namespace Tempest\Testing\Tests;

use DateTime;
use ReflectionMethod;
use Tempest\Testing\After;
use Tempest\Testing\Before;
use Tempest\Testing\Provide;
use Tempest\Testing\Test;

use function Tempest\Testing\test;

final class TestMetadataTest
{
    #[Test]
    public function location_includes_the_declaring_file_and_start_line(): void
    {
        $test = Test::fromName(TestMetadataFixture::class . '::metadataSubject');
        $reflection = new ReflectionMethod(TestMetadataFixture::class, 'metadataSubject');

        test($test->location)->is($reflection->getFileName() . ':' . $reflection->getStartLine());
    }

    #[Test]
    public function location_falls_back_when_no_start_line_is_available(): void
    {
        $test = Test::fromName(DateTime::class . '::format');

        test($test->location)->is(DateTime::class);
    }

    #[Test]
    public function from_name_resolves_test_metadata(): void
    {
        $test = Test::fromName(TestMetadataFixture::class . '::metadataSubject');
        $reflection = new ReflectionMethod(TestMetadataFixture::class, 'metadataSubject');

        test($test->handler->getName())->is('metadataSubject');
        test($test->name)->is(TestMetadataFixture::class . '::metadataSubject');
        test($test->location)->is($reflection->getFileName() . ':' . $reflection->getStartLine());
        test(array_map(fn ($method) => $method->getName(), $test->before))->is(['firstBefore', 'secondBefore']);
        test(array_map(fn ($method) => $method->getName(), $test->after))->is(['secondAfter', 'firstAfter']);
        test($test->provide)->is(['metadataProvider', ['value' => 'inline']]);
    }
}

final class TestMetadataFixture
{
    #[Before]
    public function firstBefore(): void {}

    #[Before]
    public function secondBefore(): void {}

    #[After]
    public function firstAfter(): void {}

    #[After]
    public function secondAfter(): void {}

    #[
        Test,
        Provide(
            'metadataProvider',
            ['value' => 'inline'],
        ),
    ]
    private function metadataSubject(string $value): void {}

    public function metadataProvider(): array
    {
        return [['value' => 'provided']];
    }
}
