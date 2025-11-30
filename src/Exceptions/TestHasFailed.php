<?php

namespace Tempest\Testing\Exceptions;

use Exception;

final class TestHasFailed extends Exception implements TestException
{
    public string $reason;
    public string $location;

    public function __construct(
        string $reason,
        mixed ...$data,
    ) {
        foreach ($data as $key => $value) {
            $data[$key] = $this->export($value);
        }

        $this->reason = sprintf($reason, ...$data);

        $trace = $this->getTrace();

        foreach ($this->getTrace() as $key => $traceEntry) {
            if (str_starts_with($trace[$key + 1]['class'] ?? null, 'Tempest\Testing\Tester')) {
                continue;
            }

            $this->location = sprintf('%s:%d', $traceEntry['file'], $traceEntry['line']);

            break;
        }

        parent::__construct($this->reason);
    }

    private function export(mixed $value): string
    {
        if (is_object($value)) {
            return $value::class;
        }

        return var_export($value, true);
    }
}