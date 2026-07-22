<?php

namespace Tempest\Testing\Testers\Database;

use Tempest\Container\Container;
use Tempest\Database\MigratesUp;
use Tempest\Database\Migrations\MigrationManager;

use function Tempest\Database\query;
use function Tempest\Testing\test;

final readonly class DatabaseTester
{
    public function __construct(
        private Container $container,
    ) {}

    public function setup(bool $migrate = true): self
    {
        return $this->reset($migrate);
    }

    public function reset(bool $migrate = true): self
    {
        $migrationManager = $this->container->get(MigrationManager::class);
        $migrationManager->dropAll();

        if ($migrate) {
            $this->migrate();
        }

        return $this;
    }

    public function migrate(string|object ...$migrationClasses): void
    {
        $migrationManager = $this->container->get(MigrationManager::class);

        if ($migrationClasses === []) {
            $migrationManager->up();
            return;
        }

        foreach ($migrationClasses as $migrationClass) {
            if (is_string($migrationClass) && class_exists($migrationClass)) {
                $migration = $this->container->get($migrationClass);
            } else {
                $migration = $migrationClass;
            }

            if (! $migration instanceof MigratesUp) {
                continue;
            }

            $migrationManager->executeUp($migration);
        }
    }

    public function assertTableHasRow(string $table, mixed ...$data): self
    {
        $select = query($table)->count();

        foreach ($data as $key => $value) {
            $select->whereField((string) $key, $value);
        }

        test($select->execute() > 0)->isTrue('Failed asserting that a row in the table %s matches the given data.', $table);

        return $this;
    }

    public function assertTableHasCount(string $table, int $count): self
    {
        test(query($table)->count()->execute())->is($count, 'Failed asserting that the table %s contains %s rows.', $table, $count);

        return $this;
    }

    public function assertTableDoesNotHaveRow(string $table, mixed ...$data): self
    {
        $select = query($table)->count();

        foreach ($data as $key => $value) {
            $select->whereField((string) $key, $value);
        }

        test($select->execute() === 0)->isTrue('Failed asserting that no row in the table %s matches the given data.', $table);

        return $this;
    }

    public function assertTableEmpty(string $table): self
    {
        return $this->assertTableHasCount($table, count: 0);
    }

    public function assertTableNotEmpty(string $table): self
    {
        return $this->assertTableHasRow($table);
    }
}
