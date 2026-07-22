<?php

namespace Tempest\Testing\Testers\Cache;

use RuntimeException;
use Tempest\Cache\Cache;
use Tempest\Cache\CacheInitializer;
use Tempest\Cache\Testing\RestrictedCacheInitializer;
use Tempest\Clock\Clock;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Support\Str;
use UnitEnum;

final readonly class CacheTester
{
    public function __construct(
        private Container $container,
    ) {}

    public function fake(string|UnitEnum|null $tag = null): TestingCache
    {
        $parsedTag = Str\parse($tag) ?? 'default';

        $cache = new TestingCache(
            tag: Str\to_kebab_case($parsedTag),
            clock: $this->container->get(Clock::class)->toPsrClock(),
        );

        $this->container->singleton(Cache::class, $cache, $tag);

        return $cache;
    }

    public function preventUsageWithoutFake(): void
    {
        if (! $this->container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to prevent usage without fake.');
        }

        $this->container->unregister(Cache::class, tagged: true);
        $this->container->removeInitializer(CacheInitializer::class);
        $this->container->addInitializer(RestrictedCacheInitializer::class);
    }
}
