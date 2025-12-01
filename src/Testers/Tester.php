<?php

namespace Tempest\Testing\Testers;

use Closure;
use Tempest\Testing\Exceptions\TestHasFailed;
use Throwable;
use function Tempest\Testing\test;

final readonly class Tester
{
    public function __construct(
        private mixed $subject = null,
    ) {}

    public function dump(): self
    {
        lw($this->subject);

        return $this;
    }

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
        if (is_string($this->subject) && ! str_contains($this->subject, $search)) {
            throw new TestHasFailed("failed asserting that string contains %s", $search);
        }

        if (is_array($this->subject) && ! in_array($search, $this->subject)) {
            throw new TestHasFailed("failed asserting that array contains %s", $search);
        }

        return $this;
    }

    public function containsNot(mixed $search): self
    {
        if (is_string($this->subject) && str_contains($this->subject, $search)) {
            throw new TestHasFailed("failed asserting that string does not contain %s", $search);
        }

        if (is_array($this->subject) && in_array($search, $this->subject)) {
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

// TODO
// final public static function assertArrayHasKey(mixed $key, array|ArrayAccess $array, string $message = ''): void
// final public static function assertArrayNotHasKey(mixed $key, array|ArrayAccess $array, string $message = ''): void
// final public static function assertIsList(mixed $array, string $message = ''): void
// final public static function assertContains(mixed $needle, iterable $haystack, string $message = ''): void
// final public static function assertNotContains(mixed $needle, iterable $haystack, string $message = ''): void
// final public static function assertCount(int $expectedCount, Countable|iterable $haystack, string $message = ''): void
// final public static function assertNotCount(int $expectedCount, Countable|iterable $haystack, string $message = ''): void
// final public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
// final public static function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
// final public static function assertEmpty(mixed $actual, string $message = ''): void
// final public static function assertNotEmpty(mixed $actual, string $message = ''): void
// final public static function assertGreaterThan(mixed $minimum, mixed $actual, string $message = ''): void
// final public static function assertGreaterThanOrEqual(mixed $minimum, mixed $actual, string $message = ''): void
// final public static function assertLessThan(mixed $maximum, mixed $actual, string $message = ''): void
// final public static function assertLessThanOrEqual(mixed $maximum, mixed $actual, string $message = ''): void
// final public static function assertTrue(mixed $condition, string $message = ''): void
// final public static function assertFalse(mixed $condition, string $message = ''): void
// final public static function assertNull(mixed $actual, string $message = ''): void
// final public static function assertNotNull(mixed $actual, string $message = ''): void
// final public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
// final public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
// final public static function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
// final public static function assertNotInstanceOf(string $expected, mixed $actual, string $message = ''): void
// final public static function assertIsArray(mixed $actual, string $message = ''): void
// final public static function assertIsBool(mixed $actual, string $message = ''): void
// final public static function assertIsFloat(mixed $actual, string $message = ''): void
// final public static function assertIsInt(mixed $actual, string $message = ''): void
// final public static function assertIsNumeric(mixed $actual, string $message = ''): void
// final public static function assertIsObject(mixed $actual, string $message = ''): void
// final public static function assertIsResource(mixed $actual, string $message = ''): void
// final public static function assertIsString(mixed $actual, string $message = ''): void
// final public static function assertIsScalar(mixed $actual, string $message = ''): void
// final public static function assertIsCallable(mixed $actual, string $message = ''): void
// final public static function assertIsIterable(mixed $actual, string $message = ''): void
// final public static function assertIsNotArray(mixed $actual, string $message = ''): void
// final public static function assertIsNotBool(mixed $actual, string $message = ''): void
// final public static function assertIsNotFloat(mixed $actual, string $message = ''): void
// final public static function assertIsNotInt(mixed $actual, string $message = ''): void
// final public static function assertIsNotNumeric(mixed $actual, string $message = ''): void
// final public static function assertIsNotObject(mixed $actual, string $message = ''): void
// final public static function assertIsNotResource(mixed $actual, string $message = ''): void
// final public static function assertIsNotString(mixed $actual, string $message = ''): void
// final public static function assertIsNotScalar(mixed $actual, string $message = ''): void
// final public static function assertIsNotCallable(mixed $actual, string $message = ''): void
// final public static function assertIsNotIterable(mixed $actual, string $message = ''): void
// final public static function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
// final public static function assertStringStartsNotWith(string $prefix, string $string, string $message = ''): void
// final public static function assertStringEndsWith(string $suffix, string $string, string $message = ''): void
// final public static function assertStringEndsNotWith(string $suffix, string $string, string $message = ''): void
// final public static function assertJson(string $actual, string $message = ''): void
}