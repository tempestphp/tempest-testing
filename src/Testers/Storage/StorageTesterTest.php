<?php

namespace Tempest\Testing\Testers\Storage;

use DateTimeImmutable;
use Tempest\Container\Container;
use Tempest\Storage\Storage;
use Tempest\Storage\StorageUsageWasForbidden;
use Tempest\Testing\Test;

use function Tempest\Testing\test;

final class StorageTesterTest
{
    use TestsStorage;

    #[Test]
    public function fake_registers_testing_storage(Container $container): void
    {
        $storage = $this->storage->fake();

        test($storage)->instanceOf(TestingStorage::class);
        test($container->get(Storage::class))->is($storage);

        $storage
            ->write('docs/readme.txt', 'Tempest testing storage')
            ->createDirectory('empty');

        $storage
            ->assertFileExists('docs/readme.txt')
            ->assertSee('docs/readme.txt', 'testing')
            ->assertDontSee('docs/readme.txt', 'missing')
            ->assertFileDoesNotExist('docs/missing.txt')
            ->assertDirectoryExists('docs')
            ->assertDirectoryNotEmpty('docs')
            ->assertDirectoryEmpty('empty')
            ->assertDirectoryDoesNotExist('missing')
            ->assertFileOrDirectoryExists('docs/readme.txt')
            ->assertFileOrDirectoryDoesNotExist('docs/missing.txt');
    }

    #[Test]
    public function fake_can_be_tagged(Container $container): void
    {
        $storage = $this->storage->fake('avatars');

        test($container->get(Storage::class, 'avatars'))->is($storage);
    }

    #[Test]
    public function fake_supports_custom_url_generators(): void
    {
        $storage = $this->storage->fake();
        $expiresAt = new DateTimeImmutable('@1893456000');

        $storage->createPublicUrlsUsing(fn (string $path): string => "https://cdn.test/{$path}");
        $storage->createTemporaryUrlsUsing(fn (string $path, DateTimeImmutable $expiresAt): string => "https://signed.test/{$path}?expires={$expiresAt->getTimestamp()}");

        test($storage->publicUrl('docs/readme.txt'))->is('https://cdn.test/docs/readme.txt');
        test($storage->temporaryUrl('docs/readme.txt', $expiresAt))->is('https://signed.test/docs/readme.txt?expires=1893456000');
    }

    #[Test]
    public function prevent_usage_without_fake_restricts_storage_resolution(Container $container): void
    {
        $this->storage->preventUsageWithoutFake();

        test(fn () => $container->get(Storage::class)->fileExists('docs/readme.txt'))
            ->exceptionThrown(StorageUsageWasForbidden::class);

        $storage = $this->storage->fake();
        $storage->write('docs/readme.txt', 'Tempest');

        $storage->assertFileExists('docs/readme.txt');
    }
}
