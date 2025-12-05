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

    public function fail(?string $reason = null, mixed ...$reasonData): never
    {
        $reason ??= 'test was marked as failed';

        throw new TestHasFailed($reason, ...$reasonData);
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

    public function is(mixed $expected, ?string $reason = null, mixed ...$reasonData): self
    {
        if ($expected !== $this->subject) {
            $this->fail(
                $reason ?? "failed asserting that %s is %s",
                ...($reasonData ?: [
                    $this->subject,
                    $expected,
                ]),
            );
        }

        return $this;
    }

    public function isNot(mixed $expected, ?string $reason = null, mixed ...$reasonData): self
    {
        if ($expected === $this->subject) {
            $this->fail(
                $reason ?? "failed asserting that %s is not %s",
                ...($reasonData ?: [$this->subject, $expected]),
            );
        }

        return $this;
    }

    public function isEqualTo(mixed $expected, ?string $reason = null, mixed ...$reasonData): self
    {
        if ($expected != $this->subject) {
            $this->fail(
                $reason ?? "failed asserting that %s is equal to %s",
                ...($reasonData ?: [$this->subject, $expected]),
            );
        }

        return $this;
    }

    public function isNotEqualTo(mixed $expected, ?string $reason = null, mixed ...$reasonData): self
    {
        if ($expected == $this->subject) {
            $this->fail(
                $reason ?? "failed asserting that %s is not equal to %s",
                ...($reasonData ?: [$this->subject, $expected]),
            );
        }

        return $this;
    }

    public function isCallable(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_callable($this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s is callable",
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotCallable(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_callable($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not callable',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function hasCount(int $expected, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isCountable();

        if ($expected !== count($this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s has %s items",
                ...($reasonData ?: [$this->subject, $expected]),
            );
        }

        return $this;
    }

    public function hasNotCount(int $expected, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isCountable();

        if ($expected === count($this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s does not have %s items",
                ...($reasonData ?: [$this->subject, $expected]),
            );
        }

        return $this;
    }

    public function isCountable(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_countable($this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s is countable",
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotCountable(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_countable($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not countable',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function contains(mixed $search, ?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_string($this->subject) && ! is_array($this->subject)) {
            $this->fail(
                $reason ?? 'to check contains, the test subject must be a string or an array; instead got %s',
                ...($reasonData ?: [$this->subject]),
            );
        }

        if (is_string($this->subject) && ! str_contains($this->subject, $search)) {
            $this->fail(
                $reason ?? "failed asserting that %s contains %s",
                ...($reasonData ?: [$this->subject, $search]),
            );
        }

        if (is_array($this->subject) && ! in_array($search, $this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s contains %s",
                ...($reasonData ?: [$this->subject, $search]),
            );
        }

        return $this;
    }

    public function containsNot(mixed $search, ?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_string($this->subject) && ! is_array($this->subject)) {
            $this->fail(
                $reason ?? 'to check contains, the test subject must be a string or an array; instead got %s',
                ...($reasonData ?: [$this->subject]),
            );
        }

        if (is_string($this->subject) && str_contains($this->subject, $search)) {
            $this->fail(
                $reason ?? "failed asserting that %s does not contain %s",
                ...($reasonData ?: [$this->subject, $search]),
            );
        }

        if (is_array($this->subject) && in_array($search, $this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s does not contain %s",
                ...($reasonData ?: [$this->subject, $search]),
            );
        }

        return $this;
    }

    public function hasKey(mixed $key, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isArray();

        if (! array_key_exists($key, $this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s has key %s",
                ...($reasonData ?: [$this->subject, $key]),
            );
        }

        return $this;
    }

    public function missesKey(mixed $key, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isArray();

        if (array_key_exists($key, $this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s does not have key %s",
                ...($reasonData ?: [$this->subject, $key]),
            );
        }

        return $this;
    }

    public function instanceOf(string $expectedClass, ?string $reason = null, mixed ...$reasonData): self
    {
        if (! $this->subject instanceof $expectedClass) {
            $this->fail(
                $reason ?? "failed asserting that %s is an instance of %s",
                ...($reasonData ?: [$this->subject, $expectedClass]),
            );
        }

        return $this;
    }

    public function isNotInstanceOf(string $expectedClass, ?string $reason = null, mixed ...$reasonData): self
    {
        if ($this->subject instanceof $expectedClass) {
            $this->fail(
                $reason ?? "failed asserting that %s is not an instance of %s",
                ...($reasonData ?: [$this->subject, $expectedClass]),
            );
        }

        return $this;
    }

    public function exceptionThrown(
        string $expectedExceptionClass,
        ?Closure $exceptionTester = null,
        ?string $reason = null,
        mixed ...$reasonData
    ): self
    {
        if (! is_callable($this->subject)) {
            $this->fail(
                $reason ?? 'to test exceptions, the test subject must be a callable; instead got %s',
                ...($reasonData ?: [$this->subject]),
            );
        }

        try {
            ($this->subject)();
        } catch (Throwable $throwable) {
            if (! $throwable instanceof $expectedExceptionClass) {
                $this->fail(
                    $reason ?? "Expected exception %s was not thrown, instead got %s",
                    ...($reasonData ?: [$expectedExceptionClass, $throwable::class]),
                );
            }

            if ($exceptionTester) {
                $exceptionTester($throwable);
            }

            return $this;
        }

        $this->fail(
            $reason ?? "Expected exception %s was not thrown",
            ...($reasonData ?: [$expectedExceptionClass]),
        );
    }

    public function exceptionNotThrown(string $expectedExceptionClass, ?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_callable($this->subject)) {
            return $this;
        }

        try {
            ($this->subject)();
        } catch (Throwable $throwable) {
            if ($throwable instanceof $expectedExceptionClass) {
                $this->fail(
                    $reason ?? "Exception %s was thrown, while it shouldn't",
                    ...($reasonData ?: [$throwable::class]),
                );
            }
        }

        return $this;
    }

    public function isList(?string $reason = null, mixed ...$reasonData): self
    {
        $this->isArray();

        if (! array_is_list($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is a list',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotList(?string $reason = null, mixed ...$reasonData): self
    {
        $this->isArray();

        if (array_is_list($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not a list',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isEmpty(?string $reason = null, mixed ...$reasonData): self
    {
        if (! empty($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is empty',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotEmpty(?string $reason = null, mixed ...$reasonData): self
    {
        if (empty($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not empty',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function greaterThan(int|float $minimum, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isNumeric();

        if ($this->subject <= $minimum) {
            $this->fail(
                $reason ?? 'failed asserting that %s is greater than %s',
                ...($reasonData ?: [$this->subject, $minimum]),
            );
        }

        return $this;
    }

    public function greaterThanOrEqual(int|float $minimum, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isNumeric();

        if ($this->subject < $minimum) {
            $this->fail(
                $reason ?? 'failed asserting that %s is greater than or equal to %s',
                ...($reasonData ?: [$this->subject, $minimum]),
            );
        }

        return $this;
    }

    public function lessThan(int|float $maximum, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isNumeric();

        if ($this->subject >= $maximum) {
            $this->fail(
                $reason ?? 'failed asserting that %s is less than %s',
                ...($reasonData ?: [$this->subject, $maximum]),
            );
        }

        return $this;
    }

    public function lessThanOrEqual(int|float $maximum, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isNumeric();

        if ($this->subject > $maximum) {
            $this->fail(
                $reason ?? 'failed asserting that %s is less than or equal to %s',
                ...($reasonData ?: [$this->subject, $maximum]),
            );
        }

        return $this;
    }

    public function isTrue(?string $reason = null, mixed ...$reasonData): self
    {
        if ($this->subject !== true) {
            $this->fail(
                $reason ?? 'failed asserting that %s is true',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isFalse(?string $reason = null, mixed ...$reasonData): self
    {
        if ($this->subject !== false) {
            $this->fail(
                $reason ?? 'failed asserting that %s is false',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isTrueish(?string $reason = null, mixed ...$reasonData): self
    {
        if (((bool)$this->subject) !== true) {
            $this->fail(
                $reason ?? 'failed asserting that %s is trueish',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isFalseish(?string $reason = null, mixed ...$reasonData): self
    {
        if (((bool)$this->subject) !== false) {
            $this->fail(
                $reason ?? 'failed asserting that %s is falseish',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNull(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_null($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is null',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotNull(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_null($this->subject)) {
            $this->fail(
                $reason ?? "failed asserting that %s is not null",
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isArray(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_array($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is array',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotArray(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_array($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not array',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isBool(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_bool($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is bool',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotBool(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_bool($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not bool',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isFloat(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_float($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is float',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotFloat(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_float($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not float',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isInt(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_int($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is int',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotInt(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_int($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not int',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNumeric(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_numeric($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is numeric',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotNumeric(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_numeric($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not numeric',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isObject(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_object($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is object',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotObject(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_object($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not object',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isResource(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_resource($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is resource',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotResource(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_resource($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not resource',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isString(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_string($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is string',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotString(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_string($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not string',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isScalar(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_scalar($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is scalar',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotScalar(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_scalar($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not scalar',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isIterable(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_iterable($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is iterable',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotIterable(?string $reason = null, mixed ...$reasonData): self
    {
        if (is_iterable($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not iterable',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function startsWith(string $prefix, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isString();

        if (! str_starts_with($this->subject, $prefix)) {
            $this->fail(
                $reason ?? 'failed asserting that %s starts with %s',
                ...($reasonData ?: [$this->subject, $prefix]),
            );
        }

        return $this;
    }

    public function startsNotWith(string $prefix, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isString();

        if (str_starts_with($this->subject, $prefix)) {
            $this->fail(
                $reason ?? 'failed asserting that %s does not start with %s',
                ...($reasonData ?: [$this->subject, $prefix]),
            );
        }

        return $this;
    }

    public function endsWith(string $suffix, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isString();

        if (! str_ends_with($this->subject, $suffix)) {
            $this->fail(
                $reason ?? 'failed asserting that %s ends with %s',
                ...($reasonData ?: [$this->subject, $suffix]),
            );
        }

        return $this;
    }

    public function endsNotWith(string $suffix, ?string $reason = null, mixed ...$reasonData): self
    {
        $this->isString();

        if (str_ends_with($this->subject, $suffix)) {
            $this->fail(
                $reason ?? 'failed asserting that %s does not end with %s',
                ...($reasonData ?: [$this->subject, $suffix]),
            );
        }

        return $this;
    }

    public function isJson(?string $reason = null, mixed ...$reasonData): self
    {
        $this->isString();

        if (! json_validate($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is valid JSON',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }

    public function isNotJson(?string $reason = null, mixed ...$reasonData): self
    {
        if (! is_string($this->subject)) {
            return $this;
        }

        if (json_validate($this->subject)) {
            $this->fail(
                $reason ?? 'failed asserting that %s is not valid JSON',
                ...($reasonData ?: [$this->subject]),
            );
        }

        return $this;
    }
}