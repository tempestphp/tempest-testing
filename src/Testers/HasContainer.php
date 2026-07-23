<?php

namespace Tempest\Testing\Testers;

use Tempest\Container\Container;
use Tempest\Testing\Before;

trait HasContainer
{
    protected Container $container;

    #[Before]
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }
}
