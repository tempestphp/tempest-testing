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

    public function fails(?string $message = null): self
    {
        $exceptionTester = null;

        if ($message) {
            $exceptionTester = function (TestHasFailed $exception) use ($message) {
                test($exception->getMessage())->is($message);
            };
        }

        $this->exceptionThrown(
            expectedExceptionClass: TestHasFailed::class,
            exceptionTester: $exceptionTester,
        );

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
        if ($expected === $this->subject) {
            throw new TestHasFailed("failed asserting that %s is not %s", $this->subject, $expected);
        }

        return $this;
    }

    public function isEqualTo(mixed $expected): self
    {
        if ($expected != $this->subject) {
            throw new TestHasFailed("failed asserting that %s is equal to %s", $this->subject, $expected);
        }

        return $this;
    }

    public function isNotEqualTo(mixed $expected): self
    {
        if ($expected == $this->subject) {
            throw new TestHasFailed("failed asserting that %s is not equal to %s", $this->subject, $expected);
        }

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
            throw new TestHasFailed("failed asserting that array has %s items", $expected);
        }

        return $this;
    }

    public function hasNotCount(int $expected): self
    {
        if ($expected === count($this->subject)) {
            throw new TestHasFailed("failed asserting that array does not have %s items", $expected);
        }

        return $this;
    }

    public function contains(mixed $search): self
    {
        if (! in_array($search, $this->subject)) {
            throw new TestHasFailed("failed asserting that array contains %s", $search);
        }

        return $this;
    }

    public function containsNot(mixed $search): self
    {
        if (in_array($search, $this->subject)) {
            throw new TestHasFailed("failed asserting that array does not contain %s", $search);
        }

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
        if ($this->subject instanceof $expectedClass) {
            throw new TestHasFailed("failed asserting that %s is not an instance of %s", $this->subject, $expectedClass);
        }

        return $this;
    }

    public function exceptionThrown(
        string $expectedExceptionClass,
        ?Closure $exceptionTester = null,
    ): self
    {
        if (! is_callable($this->subject)) {
            throw new TestHasFailed("to test exceptions, the test subject must be a callable; instead got %s", $this->subject);
        }

        try {
            ($this->subject)();
        } catch (Throwable $throwable) {
            if (! $throwable instanceof $expectedExceptionClass) {
                throw new TestHasFailed("Expected exception %s was not thrown, instead got %s", $expectedExceptionClass, $throwable::class);
            }

            if ($exceptionTester) {
                $exceptionTester($throwable);
            }

            return $this;
        }

        throw new TestHasFailed("Expected exception %s was not thrown", $expectedExceptionClass);

        return $this;
    }

    public function exceptionNotThrown(string $expectedExceptionClass): self
    {
        if (! is_callable($this->subject)) {
            return $this;
        }

        try {
            ($this->subject)();
        } catch (Throwable $throwable) {
            if ($throwable instanceof $expectedExceptionClass) {
                throw new TestHasFailed("Exception %s was thrown, while it shouldn't", $throwable::class);
            }
        }

        return $this;
    }
}