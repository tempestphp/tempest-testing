<?php

namespace Tempest\Testing\Tests;

use Tempest\Testing\Plugins\TestsEvents;
use Tempest\Testing\Test;
use function Tempest\Testing\test;

final class BeforeAndAfterTest
{
    use TestsEvents;

    #[Test]
    public function test_before(): void
    {
        test()->succeed();
    }
}