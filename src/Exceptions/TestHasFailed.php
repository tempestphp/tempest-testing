<?php

namespace Tempest\Testing\Exceptions;

use Exception;
use UnitEnum;

final class TestHasFailed extends Exception implements TestException
{
    public string $reason;
    public string $location;

    public function __construct(
        string $reason,
        mixed ...$data,
    ) {
        $parsedData = [];

        foreach ($data as $value) {
            $parsedData[] = '`' . $this->export($value) . '`';
        }

        $this->reason = sprintf($reason, ...$parsedData);

        $trace = $this->getTrace();

        foreach ($trace as $key => $traceEntry) {
            $nextKey = is_int($key) ? $key + 1 : null;
            $nextClass = $nextKey === null ? null : $trace[$nextKey]['class'] ?? null;

            if (is_string($nextClass) && str_starts_with($nextClass, 'Tempest\Testing\Testers\PrimitiveTester')) {
                continue;
            }

            $file = is_string($traceEntry['file'] ?? null) ? $traceEntry['file'] : 'unknown';
            $line = is_int($traceEntry['line'] ?? null) ? $traceEntry['line'] : 0;

            $this->location = sprintf('%s:%d', $file, $line);

            break;
        }

        parent::__construct($this->reason);
    }

    private function export(mixed $value): string
    {
        if ($value instanceof UnitEnum) {
            return sprintf('%s::%s', $value::class, $value->name);
        }

        if (is_object($value)) {
            return $value::class;
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_resource($value)) {
            return 'resource';
        }

        return var_export($value, true);
    }
}
