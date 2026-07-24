<?php

namespace Tempest\Testing\Tests;

use Tempest\Testing\Test;

use function Tempest\Testing\test;

final readonly class SkippedTest
{
    #[Test]
    public function skip1(): void
    {
        test()->skip();
    }

    #[Test]
    public function skip2(): void
    {
        test()->skip();
    }

    #[Test]
    public function skip3(): void
    {
        test()->skip();
    }

    #[Test]
    public function skip4(): void
    {
        test()->skip();
    }

    #[Test]
    public function skip5(): void
    {
        test()->skip();
    }

    #[Test]
    public function skip6(): void
    {
        test()->skip();
    }

    #[Test]
    public function skip7(): void
    {
        test()->skip();
    }

    #[Test]
    public function skip8(): void
    {
        test()->skip();
    }

    #[Test]
    public function skip9(): void
    {
        test()->skip();
    }
}
