<?php

namespace Tempest\Testing\Events;

use Tempest\EventBus\StopsPropagation;

#[StopsPropagation]
final class TestRunEnded
{

}