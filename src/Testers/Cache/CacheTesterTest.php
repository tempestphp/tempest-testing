<?php

namespace Tempest\Testing\Testers\Cache;

use Tempest\Cache\Cache;
use Tempest\Cache\CacheUsageWasForbidden;
use Tempest\Container\Container;
use Tempest\Testing\Test;

use function Tempest\Testing\test;

final class CacheTesterTest
{
    use TestsCache;

    #[Test]
    public function fake_registers_a_testing_cache(Container $container): void
    {
        $cache = $this->cache->fake();

        test($cache)->instanceOf(TestingCache::class);
        test($container->get(Cache::class))->is($cache);

        $cache->put('name', 'tempest');

        $cache
            ->assertKeyHasValue('name', 'tempest')
            ->assertKeyDoesNotHaveValue('name', 'testing')
            ->assertCached('name')
            ->assertNotCached('missing')
            ->assertNotEmpty();

        $cache->clear();
        $cache->assertEmpty();
    }

    #[Test]
    public function prevent_usage_without_fake_restricts_cache_resolution(Container $container): void
    {
        $this->cache->preventUsageWithoutFake();

        test(fn () => $container->get(Cache::class)->get('name'))
            ->exceptionThrown(CacheUsageWasForbidden::class);

        $cache = $this->cache->fake();
        $cache->put('name', 'tempest');

        $cache->assertKeyHasValue('name', 'tempest');
    }

    #[Test]
    public function lock_assertions_are_supported(): void
    {
        $cache = $this->cache->fake();
        $lock = $cache->lock('deploy', owner: 'test');

        $cache->assertNotLocked('deploy');

        $lock->acquire();

        $cache->assertLocked('deploy', by: 'test');

        $lock->release();

        $cache->assertNotLocked('deploy', by: 'test');
    }
}
