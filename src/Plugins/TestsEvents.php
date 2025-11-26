<?php

namespace Tempest\Testing\Plugins;

use Tempest\Testing\Before;

trait TestsEvents
{
    private EventTester $events;

    #[Before]
    public function setupEventTester(): void
    {
        $this->events = new EventTester();
    }
}