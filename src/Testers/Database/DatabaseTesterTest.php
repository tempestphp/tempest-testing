<?php

namespace Tempest\Testing\Testers\Database;

use Tempest\Container\Container;
use Tempest\Database\Config\DatabaseConfig;
use Tempest\Database\Config\SQLiteConfig;
use Tempest\Database\Database;
use Tempest\Database\Query;
use Tempest\Database\QueryStatements\CreateTableStatement;
use Tempest\Testing\Test;
use function Tempest\Database\query;
use function Tempest\Testing\test;

final class DatabaseTesterTest
{
    use TestsDatabase;

    #[Test]
    public function asserts_table_rows(Container $container): void
    {
        $this->useMemoryDatabase($container);
        $this->createEntriesTable($container->get(Database::class));

        query('database_tester_entries')->insert(name: 'tempest', age: 3)->execute();
        query('database_tester_entries')->insert(name: 'testing', age: 1)->execute();

        $this->database
            ->assertTableHasRow('database_tester_entries', name: 'tempest')
            ->assertTableHasRow('database_tester_entries', age: 1)
            ->assertTableDoesNotHaveRow('database_tester_entries', name: 'missing')
            ->assertTableHasCount('database_tester_entries', 2)
            ->assertTableNotEmpty('database_tester_entries');
    }

    #[Test]
    public function asserts_empty_tables(Container $container): void
    {
        $this->useMemoryDatabase($container);
        $this->createEntriesTable($container->get(Database::class));

        $this->database
            ->assertTableEmpty('database_tester_entries')
            ->assertTableHasCount('database_tester_entries', 0);
    }

    #[Test]
    public function failures_use_the_tempest_test_runner(Container $container): void
    {
        $this->useMemoryDatabase($container);
        $this->createEntriesTable($container->get(Database::class));

        test(fn () => $this->database->assertTableHasRow('database_tester_entries', name: 'missing'))
            ->fails('Failed asserting that a row in the table `\'database_tester_entries\'` matches the given data.');

        test(fn () => $this->database->assertTableHasCount('database_tester_entries', 1))
            ->fails('Failed asserting that the table `\'database_tester_entries\'` contains `1` rows.');
    }

    private function useMemoryDatabase(Container $container): void
    {
        $container->singleton(DatabaseConfig::class, new SQLiteConfig(path: ':memory:'));
    }

    private function createEntriesTable(Database $database): void
    {
        $database->execute(new Query('DROP TABLE IF EXISTS database_tester_entries'));

        $database->execute(new Query(
            new CreateTableStatement('database_tester_entries')
                ->primary()
                ->string('name')
                ->integer('age'),
        ));
    }
}
