<?php

namespace Tempest\Testing\Testers\Cache;

use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Testing\After;
use Tempest\Testing\Before;

trait TestsCache
{
    protected CacheTester $cache;

    private array $testsCacheOriginalSingletons = [];

    private array $testsCacheOriginalDynamicInitializers = [];

    #[Before]
    public function testsCacheBefore(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to test cache.');
        }

        $this->testsCacheOriginalSingletons = $container->getSingletons();
        $this->testsCacheOriginalDynamicInitializers = $container->getDynamicInitializers();

        $this->cache = new CacheTester($container);
    }

    #[After]
    public function testsCacheAfter(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            return;
        }

        $container->setSingletons($this->testsCacheOriginalSingletons);
        $container->setDynamicInitializers($this->testsCacheOriginalDynamicInitializers);
    }
}
