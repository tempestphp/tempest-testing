<?php

namespace Tempest\Testing\Events;

use Tempest\EventBus\StopsPropagation;
use Tempest\Testing\Output\ConvertsToTeamcityMessage;
use Tempest\Testing\Output\TeamcityMessage;
use Tempest\Testing\Output\TeamcityMessageName;

#[StopsPropagation]
final class TestFinished implements DispatchToParentProcess, ConvertsToTeamcityMessage
{
    public function __construct(
        public string $name,
        public float $duration = 0.0,
    ) {}

    public TeamcityMessage $teamcityMessage {
        get => new TeamcityMessage(
            TeamcityMessageName::TEST_FINISHED,
            [
                'name' => $this->name,
                'duration' => (string) (int) round($this->duration),
            ],
        );
    }

    public function serialize(): array
    {
        return [
            'name' => $this->name,
            'duration' => $this->duration,
        ];
    }

    public static function deserialize(array $data): DispatchToParentProcess
    {
        $duration = $data['duration'] ?? 0.0;

        if (! is_int($duration) && ! is_float($duration) && ! is_string($duration)) {
            $duration = 0.0;
        }

        return new self(
            name: $data['name'],
            duration: (float) $duration,
        );
    }
}
