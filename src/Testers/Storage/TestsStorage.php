<?php

namespace Tempest\Testing\Testers\Storage;

use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Testing\After;
use Tempest\Testing\Before;

trait TestsStorage
{
    protected StorageTester $storage;

    private array $testsStorageOriginalSingletons = [];

    private array $testsStorageOriginalDynamicInitializers = [];

    #[Before]
    public function testsStorageBefore(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to test storage.');
        }

        $this->testsStorageOriginalSingletons = $container->getSingletons();
        $this->testsStorageOriginalDynamicInitializers = $container->getDynamicInitializers();

        $this->storage = new StorageTester($container);
    }

    #[After]
    public function testsStorageAfter(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            return;
        }

        $container->setSingletons($this->testsStorageOriginalSingletons);
        $container->setDynamicInitializers($this->testsStorageOriginalDynamicInitializers);
    }
}
