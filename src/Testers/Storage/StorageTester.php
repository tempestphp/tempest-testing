<?php

namespace Tempest\Testing\Testers\Storage;

use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Container\Singleton;
use Tempest\Storage\Storage;
use Tempest\Storage\StorageInitializer;
use Tempest\Support\Str;
use UnitEnum;

#[Singleton]
final readonly class StorageTester
{
    public function __construct(
        private Container $container,
    ) {}

    public function fake(string|UnitEnum|null $tag = null, bool $persist = false): TestingStorage
    {
        $path = Str\parse($tag, default: 'default') ?? 'default';

        $storage = new TestingStorage(
            path: Str\to_kebab_case($path),
        );

        $this->container->singleton(Storage::class, $storage, $tag);

        return $persist
            ? $storage->createDirectory()
            : $storage->cleanDirectory();
    }

    public function preventUsageWithoutFake(): void
    {
        if (! $this->container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to prevent usage without fake.');
        }

        $this->container->unregister(Storage::class, tagged: true);
        $this->container->removeInitializer(StorageInitializer::class);
        $this->container->addInitializer(RestrictedStorageInitializer::class);
    }
}
