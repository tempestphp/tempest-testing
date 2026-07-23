<?php

namespace Tempest\Testing;

final readonly class TestEnvironment
{
    public function __construct(
        public bool $verbose = false,
        public bool $debug = false,
        public bool $failFast = false,
    ) {}
}
