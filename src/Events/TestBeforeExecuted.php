<?php

namespace Tempest\Testing\Events;

use Tempest\Reflection\MethodReflector;
use Tempest\Testing\Test;

final readonly class TestBeforeExecuted
{
    public function __construct(
        public Test $test,
        public MethodReflector $before,
    ) {}
}