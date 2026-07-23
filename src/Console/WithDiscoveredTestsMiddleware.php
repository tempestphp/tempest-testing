<?php

namespace Tempest\Testing\Console;

use Tempest\Console\ConsoleMiddleware;
use Tempest\Console\ConsoleMiddlewareCallable;
use Tempest\Console\ExitCode;
use Tempest\Console\Initializers\Invocation;
use Tempest\Container\Container;
use Tempest\Discovery\BootDiscovery;
use Tempest\Discovery\Composer;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Testing\Discovery\TestDiscovery;

use function Tempest\Support\arr;

#[SkipDiscovery]
final readonly class WithDiscoveredTestsMiddleware implements ConsoleMiddleware
{
    public function __construct(
        private Composer $composer,
        private Container $container,
    ) {}

    public function __invoke(Invocation $invocation, ConsoleMiddlewareCallable $next): ExitCode|int
    {
        $this->container->invoke(
            BootDiscovery::class,
            discoveryClasses: [
                TestDiscovery::class,
            ],
            discoveryLocations: arr($this->composer->devNamespaces)
                ->add($this->composer->mainNamespace)
                ->filter()
                ->map(DiscoveryLocation::fromNamespace(...))
                ->toArray(),
        );

        return $next($invocation);
    }
}
