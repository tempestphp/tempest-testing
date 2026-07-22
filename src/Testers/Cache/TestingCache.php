<?php

declare(strict_types=1);

namespace Tempest\Testing\Testers\Cache;

use Closure;
use Psr\Cache\CacheItemInterface;
use Psr\Clock\ClockInterface;
use Stringable;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Cache\Cache;
use Tempest\Cache\GenericCache;
use Tempest\Cache\GenericLock;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\DateTimeInterface;
use Tempest\DateTime\Duration;
use Tempest\Support\Random;
use function Tempest\Testing\test;

final class TestingCache implements Cache
{
    public bool $enabled = true {
        set {
            $this->cache->enabled = $value;
            $this->enabled = $value;
        }
    }

    private Cache $cache;

    private ArrayAdapter $adapter;

    public function __construct(
        public string $tag,
        ClockInterface $clock,
    ) {
        $this->adapter = new ArrayAdapter(clock: $clock);
        $this->cache = new GenericCache($this->adapter);
    }

    public function lock(Stringable|string $key, Duration|DateTimeInterface|null $duration = null, Stringable|string|null $owner = null): TestingLock
    {
        if ($duration instanceof DateTimeInterface) {
            $duration = $duration->since(DateTime::now());
        }

        return new TestingLock(new GenericLock(
            key: (string) $key,
            owner: $owner ? (string) $owner : Random\secure_string(length: 10),
            cache: $this->cache,
            duration: $duration,
        ));
    }

    public function has(Stringable|string $key): bool
    {
        return $this->cache->has($key);
    }

    public function put(Stringable|string $key, mixed $value, Duration|DateTimeInterface|null $expiration = null): CacheItemInterface
    {
        return $this->cache->put($key, $value, $expiration);
    }

    public function putMany(iterable $values, Duration|DateTimeInterface|null $expiration = null): array
    {
        return $this->cache->putMany($values, $expiration);
    }

    public function increment(Stringable|string $key, int $by = 1): int
    {
        return $this->cache->increment($key, $by);
    }

    public function decrement(Stringable|string $key, int $by = 1): int
    {
        return $this->cache->decrement($key, $by);
    }

    public function get(Stringable|string $key): mixed
    {
        return $this->cache->get($key);
    }

    public function getMany(iterable $key): array
    {
        return $this->cache->getMany($key);
    }

    public function resolve(Stringable|string $key, Closure $callback, Duration|DateTimeInterface|null $expiration = null, ?Duration $stale = null): mixed
    {
        return $this->cache->resolve($key, $callback, $expiration, $stale);
    }

    public function remove(Stringable|string $key): void
    {
        $this->cache->remove($key);
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    public function assertKeyHasValue(Stringable|string $key, mixed $value): self
    {
        $this->assertCached($key);

        test($this->get($key))->is($value, 'Cache key %s does not match the expected value.', $key);

        return $this;
    }

    public function assertKeyDoesNotHaveValue(Stringable|string $key, mixed $value): self
    {
        $this->assertCached($key);

        test($this->get($key))->isNot($value, 'Cache key %s matches the given value.', $key);

        return $this;
    }

    public function assertCached(Stringable|string $key, ?Closure $callback = null): self
    {
        test($this->get($key))->isNotNull('Cache key %s was not cached.', $key);

        if ($callback && false === $callback($this->get($key))) {
            test()->fail('Cache key %s failed the assertion.', $key);
        }

        return $this;
    }

    public function assertNotCached(Stringable|string $key): self
    {
        test($this->get($key))->isNull('Cache key %s was cached.', $key);

        return $this;
    }

    public function assertEmpty(): self
    {
        test($this->adapter->getValues())->isEmpty('Cache is not empty.');

        return $this;
    }

    public function assertNotEmpty(): self
    {
        test($this->adapter->getValues())->isNotEmpty('Cache is empty.');

        return $this;
    }

    public function assertLocked(string|Stringable $key, Stringable|string|null $by = null, DateTimeInterface|Duration|null $for = null): self
    {
        $this->lock($key)->assertLocked($by, $for);

        return $this;
    }

    public function assertNotLocked(string|Stringable $key, Stringable|string|null $by = null): self
    {
        $this->lock($key)->assertNotLocked($by);

        return $this;
    }
}
