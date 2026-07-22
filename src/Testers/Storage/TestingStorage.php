<?php

namespace Tempest\Testing\Testers\Storage;

use Closure;
use DateTimeInterface;
use League\Flysystem\Config;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use Tempest\Storage\Config\LocalStorageConfig;
use Tempest\Storage\DirectoryListing;
use Tempest\Storage\GenericStorage;
use Tempest\Storage\Storage;

use function Tempest\internal_storage_path;
use function Tempest\Testing\test;

final class TestingStorage implements Storage
{
    private Storage $storage;

    private ?TemporaryUrlGenerator $temporaryUrlGenerator = null;

    private ?PublicUrlGenerator $publicUrlGenerator = null;

    public function __construct(
        private readonly ?string $path = null,
        ?TemporaryUrlGenerator $temporaryUrlGenerator = null,
        ?PublicUrlGenerator $publicUrlGenerator = null,
    ) {
        $this->storage = $this->createStorage($path, $temporaryUrlGenerator, $publicUrlGenerator);
    }

    public function setTemporaryUrlGenerator(TemporaryUrlGenerator $generator): static
    {
        $this->temporaryUrlGenerator = $generator;
        $this->storage = $this->createStorage($this->path, temporaryUrlGenerator: $generator);

        return $this;
    }

    public function setPublicUrlGenerator(PublicUrlGenerator $generator): static
    {
        $this->publicUrlGenerator = $generator;
        $this->storage = $this->createStorage($this->path, publicUrlGenerator: $generator);

        return $this;
    }

    public function write(string $location, string $contents): static
    {
        $this->storage->write($location, $contents);

        return $this;
    }

    public function writeStream(string $location, mixed $contents): static
    {
        $this->storage->writeStream($location, $contents);

        return $this;
    }

    public function read(string $location): string
    {
        return $this->storage->read($location);
    }

    public function readStream(string $location): mixed
    {
        return $this->storage->readStream($location);
    }

    public function fileExists(string $location): bool
    {
        return $this->storage->fileExists($location);
    }

    public function directoryExists(string $location): bool
    {
        return $this->storage->directoryExists($location);
    }

    public function fileOrDirectoryExists(string $location): bool
    {
        return $this->storage->fileOrDirectoryExists($location);
    }

    public function delete(string $location): static
    {
        $this->storage->delete($location);

        return $this;
    }

    public function deleteDirectory(?string $location = ''): static
    {
        $this->storage->deleteDirectory($location);

        return $this;
    }

    public function createDirectory(?string $location = ''): static
    {
        $this->storage->createDirectory($location);

        return $this;
    }

    public function cleanDirectory(?string $location = ''): static
    {
        $this->storage->cleanDirectory($location);

        return $this;
    }

    public function move(string $source, string $destination): static
    {
        $this->storage->move($source, $destination);

        return $this;
    }

    public function copy(string $source, string $destination): static
    {
        $this->storage->copy($source, $destination);

        return $this;
    }

    public function fileSize(string $location): int
    {
        return $this->storage->fileSize($location);
    }

    public function lastModified(string $location): int
    {
        return $this->storage->lastModified($location);
    }

    public function mimeType(string $location): string
    {
        return $this->storage->mimeType($location);
    }

    public function setVisibility(string $location, string $visibility): static
    {
        $this->storage->setVisibility($location, $visibility);

        return $this;
    }

    public function visibility(string $location): string
    {
        return $this->storage->visibility($location);
    }

    public function publicUrl(string $location): string
    {
        return $this->storage->publicUrl($location);
    }

    public function temporaryUrl(string $location, DateTimeInterface $expiresAt): string
    {
        return $this->storage->temporaryUrl($location, $expiresAt);
    }

    public function checksum(string $location): string
    {
        return $this->storage->checksum($location);
    }

    public function list(string $location = '', bool $deep = false): DirectoryListing
    {
        return $this->storage->list($location, $deep);
    }

    public function createTemporaryUrlsUsing(Closure $closure): void
    {
        $this->setTemporaryUrlGenerator(new readonly class($closure) implements TemporaryUrlGenerator {
            public function __construct(
                private Closure $closure,
            ) {}

            public function temporaryUrl(string $path, DateTimeInterface $expiresAt, Config $config): string
            {
                return (string) ($this->closure)($path, $expiresAt);
            }
        });
    }

    public function createPublicUrlsUsing(Closure $closure): void
    {
        $this->setPublicUrlGenerator(new readonly class($closure) implements PublicUrlGenerator {
            public function __construct(
                private Closure $closure,
            ) {}

            public function publicUrl(string $path, Config $config): string
            {
                return (string) ($this->closure)($path);
            }
        });
    }

    public function assertFileExists(string $path): self
    {
        test($this->fileExists($path))->isTrue('File `%s` does not exist.', $path);

        return $this;
    }

    public function assertChecksumEquals(string $path, string $checksum): self
    {
        $this->assertFileExists($path);
        test($this->checksum($path))->is($checksum, 'File `%s` checksum does not match `%s`.', $path, $checksum);

        return $this;
    }

    public function assertSee(string $path, string $contents): self
    {
        $this->assertFileExists($path);
        test($this->read($path))->contains($contents, 'File `%s` does not contain `%s`.', $path, $contents);

        return $this;
    }

    public function assertDontSee(string $path, string $contents): self
    {
        $this->assertFileExists($path);
        test($this->read($path))->containsNot($contents, 'File `%s` contains `%s`.', $path, $contents);

        return $this;
    }

    public function assertFileDoesNotExist(string $path): self
    {
        test($this->fileExists($path))->isFalse('File `%s` exists.', $path);

        return $this;
    }

    public function assertDirectoryExists(string $path): self
    {
        test($this->directoryExists($path))->isTrue('Directory `%s` does not exist.', $path);

        return $this;
    }

    public function assertDirectoryEmpty(string $path = ''): self
    {
        $this->assertDirectoryExists($path);
        test($this->list($path)->toArray())->isEmpty('Directory `%s` is not empty.', $path);

        return $this;
    }

    public function assertDirectoryNotEmpty(string $path): self
    {
        $this->assertDirectoryExists($path);
        test($this->list($path)->toArray())->isNotEmpty('Directory `%s` is empty.', $path);

        return $this;
    }

    public function assertDirectoryDoesNotExist(string $path): self
    {
        test($this->directoryExists($path))->isFalse('Directory `%s` exists.', $path);

        return $this;
    }

    public function assertFileOrDirectoryExists(string $path): self
    {
        test($this->fileOrDirectoryExists($path))->isTrue('File or directory `%s` does not exist.', $path);

        return $this;
    }

    public function assertFileOrDirectoryDoesNotExist(string $path): self
    {
        test($this->fileOrDirectoryExists($path))->isFalse('File or directory `%s` exists.', $path);

        return $this;
    }

    private function createStorage(?string $path = null, ?TemporaryUrlGenerator $temporaryUrlGenerator = null, ?PublicUrlGenerator $publicUrlGenerator = null): Storage
    {
        return new GenericStorage(
            storageConfig: new LocalStorageConfig(
                path: internal_storage_path('tests/storage/' . ($path ?? 'storage')),
                readonly: false,
            ),
            temporaryUrlGenerator: $temporaryUrlGenerator ?? $this->temporaryUrlGenerator,
            publicUrlGenerator: $publicUrlGenerator ?? $this->publicUrlGenerator,
        );
    }
}
