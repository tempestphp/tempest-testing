<?php

namespace Tempest\Testing\Events;

use Tempest\EventBus\StopsPropagation;

#[StopsPropagation]
final readonly class TestSkipped
{
    public function __construct(
        public string $name,
    ) {}
}