<?php

namespace Tempest\Testing\Console;

use Tempest\Console\ConsoleMiddleware;
use Tempest\Console\ConsoleMiddlewareCallable;
use Tempest\Console\ExitCode;
use Tempest\Console\Initializers\Invocation;
use Tempest\Container\Container;
use Tempest\Core\Composer;
use Tempest\Core\DiscoveryCache;
use Tempest\Core\DiscoveryConfig;
use Tempest\Core\FrameworkKernel;
use Tempest\Core\Kernel;
use Tempest\Core\Kernel\LoadDiscoveryClasses;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Support\Namespace\Psr4Namespace;
use Tempest\Testing\Discovery\TestDiscovery;
use function Tempest\Support\arr;

final class WithDiscoveredTestsMiddleware implements ConsoleMiddleware
{
    public function __construct(
        private Composer $composer,
        private Kernel $kernel,
        private Container $container,
        private DiscoveryConfig $discoveryConfig,
        private DiscoveryCache $discoveryCache,
    ) {}

    public function __invoke(Invocation $invocation, ConsoleMiddlewareCallable $next): ExitCode|int
    {
        $discoveryLocations = arr($this->composer->devNamespaces)
            ->map(fn (Psr4Namespace $namespace) => DiscoveryLocation::fromNamespace($namespace));


        new LoadDiscoveryClasses(
            container: $this->container,
            discoveryConfig: $this->discoveryConfig,
            discoveryCache: $this->discoveryCache,
        )(
            discoveryClasses: [
                TestDiscovery::class,
            ],
            discoveryLocations: $discoveryLocations->toArray(),
        );

        return $next($invocation);
    }
}