<?php

namespace Tempest\Testing;

use Closure;
use Tempest\Testing\Exceptions\TestHasFailed;
use Throwable;

final readonly class Tester
{
    public function __construct(
        private mixed $subject = null,
    ) {}

    public function fail(?string $reason = null): never
    {
        throw new TestHasFailed($reason ?? 'test was marked as failed');
    }

    public function succeed(): void
    {
        return;
    }

    public function fails(): self
    {
        $this->exceptionThrown(TestHasFailed::class);

        return $this;
    }

    public function succeeds(): self
    {
        $this->isCallable();

        ($this->subject)();

        return $this;
    }

    public function is(mixed $expected): self
    {
        if ($expected !== $this->subject) {
            throw new TestHasFailed("failed asserting that %s is %s", $this->subject, $expected);
        }

        return $this;
    }

    public function isNot(mixed $expected): self
    {
        test(fn () => $this->is($expected))->fails();

        return $this;
    }

    public function isEqualTo(mixed $expected): self
    {
        if ($expected != $this->subject) {
            throw new TestHasFailed("failed asserting that %s equals %s", $this->subject, $expected);
        }

        return $this;
    }

    public function isNotEqualTo(mixed $expected): self
    {
        test(fn () => $this->isEqualTo($expected))->fails();

        return $this;
    }

    public function isCallable(): self
    {
        if (! is_callable($this->subject)) {
            throw new TestHasFailed("failed asserting that %s is callable", $this->subject);
        }

        return $this;
    }

    public function hasCount(int $expected): self
    {
        if ($expected !== count($this->subject)) {
            throw new TestHasFailed("failed asserting that array has %d items", $expected);
        }

        return $this;
    }

    public function hasNotCount(int $expected): self
    {
        test(fn () => $this->hasCount($expected))->fails();

        return $this;
    }

    public function contains(mixed $search): self
    {
        if (! in_array($search, $this->subject)) {
            throw new TestHasFailed("failed asserting that array contains %s", $search);
        }

        return $this;
    }

    public function containsNot(int $expected): self
    {
        test(fn () => $this->contains($expected))->fails();

        return $this;
    }

    public function hasKey(mixed $key): self
    {
        if (! array_key_exists($key, $this->subject)) {
            throw new TestHasFailed("failed asserting that array has key %s", $key);
        }

        return $this;
    }

    public function missesKey(mixed $key): self
    {
        if (array_key_exists($key, $this->subject)) {
            throw new TestHasFailed("failed asserting that array does not have key %s", $key);
        }

        return $this;
    }

    public function instanceOf(string $expectedClass): self
    {
        if (! $this->subject instanceof $expectedClass) {
            throw new TestHasFailed("failed asserting that %s is an instance of %s", $this->subject, $expectedClass);
        }

        return $this;
    }

    public function notInstanceOf(string $expectedClass): self
    {
        test(fn () => $this->instanceOf($expectedClass))->fails();

        return $this;
    }

    public function exceptionThrown(
        string $expectedExceptionClass,
        ?Closure $exceptionTester = null,
    ): self
    {
        $this->isCallable();

        try {
            ($this->subject)();
        } catch (Throwable $throwable) {
            test($throwable)->instanceOf($expectedExceptionClass);

            if ($exceptionTester) {
                $exceptionTester($throwable);
            }

            return $this;
        }

        $this->fail("Expected exception {$expectedExceptionClass} was not thrown");

        return $this;
    }

    public function exceptionNotThrown(string $expectedExceptionClass): self
    {
        test(fn () => $this->exceptionThrown($expectedExceptionClass))->fails();

        return $this;
    }
}