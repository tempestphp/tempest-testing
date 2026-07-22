<?php

namespace Tempest\Testing\Testers\Cache;

use Closure;
use Stringable;
use Tempest\Cache\GenericLock;
use Tempest\Cache\Lock;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\DateTimeInterface;
use Tempest\DateTime\Duration;
use function Tempest\Testing\test;

final class TestingLock implements Lock
{
    public string $key {
        get => $this->lock->key;
    }

    public ?Duration $duration {
        get => $this->lock->duration;
    }

    public string $owner {
        get => $this->lock->owner;
    }

    public function __construct(
        private readonly GenericLock $lock,
    ) {}

    public function acquire(): bool
    {
        return $this->lock->acquire();
    }

    public function locked(Stringable|string|null $by = null): bool
    {
        return $this->lock->locked($by);
    }

    public function execute(Closure $callback, DateTimeInterface|Duration|null $wait = null): mixed
    {
        return $this->lock->execute($callback, $wait);
    }

    public function release(bool $force = false): bool
    {
        return $this->lock->release($force);
    }

    public function assertLocked(Stringable|string|null $by = null, DateTimeInterface|Duration|null $for = null): self
    {
        if ($by) {
            test($this->locked($by))->isTrue('Lock %s is not being held by %s.', $this->key, $by);
        } else {
            test($this->locked($by))->isTrue('Lock %s is not being held.', $this->key);
        }

        if ($for !== null) {
            if ($for instanceof DateTimeInterface) {
                $for = $for->since(DateTime::now());
            }

            test($this->duration)->isNotNull('Expected lock %s to have a duration, but it has none.', $this->key);

            test($this->duration->getTotalSeconds())->greaterThanOrEqual(
                $for->getTotalSeconds(),
                'Expected lock %s to have a duration of at least %s seconds, but it has %s seconds.',
                $this->key,
                $for->getTotalSeconds(),
                $this->duration->getTotalSeconds(),
            );
        }

        return $this;
    }

    public function assertNotLocked(Stringable|string|null $by = null): self
    {
        if ($by) {
            test($this->locked($by))->isFalse('Lock %s is being held by %s.', $this->key, $by);
        } else {
            test($this->locked($by))->isFalse('Lock %s is being held.', $this->key);
        }

        return $this;
    }
}
