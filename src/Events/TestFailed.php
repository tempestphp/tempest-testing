<?php

namespace Tempest\Testing\Events;

use Tempest\EventBus\StopsPropagation;
use Tempest\Testing\Exceptions\TestHasFailed;

#[StopsPropagation]
final readonly class TestFailed implements DispatchToParentProcess
{
    public function __construct(
        public string $name,
        public string $reason,
        public string $location,
    ) {}

    public static function fromException(string $name, TestHasFailed $exception): self
    {
        return new self(
            name: $name,
            reason: $exception->reason,
            location: $exception->location,
        );
    }

    public function serialize(): array
    {
        return [
            'name' => $this->name,
            'reason' => $this->reason,
            'location' => $this->location,
        ];
    }

    public static function deserialize(array $data): DispatchToParentProcess
    {
        return new self(
            name: $data['name'],
            reason: $data['reason'],
            location: $data['location'],
        );
    }
}