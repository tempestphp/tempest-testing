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
        public string $location,
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
            'location' => $this->location,
            'duration' => $this->duration,
        ];
    }

    public static function deserialize(array $data): DispatchToParentProcess
    {
        $location = $data['location'] ?? '';
        $duration = $data['duration'] ?? 0.0;

        if (! is_string($location)) {
            $location = '';
        }

        if (! is_int($duration) && ! is_float($duration) && ! is_string($duration)) {
            $duration = 0.0;
        }

        return new self(
            name: $data['name'],
            location: $location,
            duration: (float) $duration,
        );
    }
}
