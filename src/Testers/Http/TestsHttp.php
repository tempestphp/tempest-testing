<?php

namespace Tempest\Testing\Testers\Http;

use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Router\ResponseSender;
use Tempest\Testing\After;
use Tempest\Testing\Before;

trait TestsHttp
{
    protected HttpTester $http;

    private array $testsHttpOriginalSingletons = [];

    #[Before]
    public function testsHttpBefore(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to test HTTP.');
        }

        $this->testsHttpOriginalSingletons = $container->getSingletons();

        $responseSender = new TestingResponseSender();

        $container->singleton(ResponseSender::class, $responseSender);
        $container->singleton(TestingResponseSender::class, $responseSender);

        $this->http = new HttpTester($container);
    }

    #[After]
    public function testsHttpAfter(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            return;
        }

        $container->setSingletons($this->testsHttpOriginalSingletons);
    }
}
