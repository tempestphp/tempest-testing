<?php

namespace Tempest\Testing\Testers\Database;

use Closure;
use PDOStatement;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Database\Config\DatabaseConfig;
use Tempest\Database\Config\MysqlConfig;
use Tempest\Database\Config\PostgresConfig;
use Tempest\Database\Config\SQLiteConfig;
use Tempest\Database\Connection\Connection;
use Tempest\Database\Connection\PDOConnection;
use Tempest\Database\Database;
use Tempest\Database\GenericDatabase;
use Tempest\Database\Query;
use Tempest\Database\QueryStatements\CreateTableStatement;
use Tempest\EventBus\EventBus;
use Tempest\Reflection\ClassReflector;
use Tempest\Testing\Runner\TestRunner;
use Tempest\Testing\Test;
use Tempest\Testing\TestEnvironment;
use UnitEnum;

use function Tempest\Database\query;
use function Tempest\Testing\test;

final class DatabaseTesterTest
{
    use TestsDatabase;

    #[Test]
    public function asserts_table_rows(Container $container): void
    {
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
    public function multiple_databases(Container $container): void
    {
        $this->clearTestingConnections();

        $runner = $container->get(TestRunner::class);
        $firstPath = $this->temporaryDatabasePath('first');
        $secondPath = $this->temporaryDatabasePath('second');
        $firstRunnerPath = $this->runnerDatabasePath($firstPath, $runner->name);
        $secondRunnerPath = $this->runnerDatabasePath($secondPath, $runner->name);

        try {
            $container->config(new SQLiteConfig(path: $firstPath, tag: 'first'));
            $container->config(new SQLiteConfig(path: $secondPath, tag: 'second'));

            $firstDatabase = $container->get(Database::class, 'first');
            $secondDatabase = $container->get(Database::class, 'second');

            if (! $firstDatabase instanceof GenericDatabase || ! $secondDatabase instanceof GenericDatabase) {
                test()->fail('Tagged databases did not resolve to generic databases.');
            }

            test($firstDatabase->connection->config->dsn)->contains($firstRunnerPath);
            test($secondDatabase->connection->config->dsn)->contains($secondRunnerPath);
            test($firstDatabase->connection)->isNot($secondDatabase->connection);

            $this->createEntriesTable($firstDatabase);
            $this->createEntriesTable($secondDatabase);

            $firstDatabase->execute(new Query(
                'INSERT INTO database_tester_entries (name, age) VALUES (:name, :age)',
                ['name' => 'first', 'age' => 1],
            ));
            $secondDatabase->execute(new Query(
                'INSERT INTO database_tester_entries (name, age) VALUES (:name, :age)',
                ['name' => 'second', 'age' => 2],
            ));

            test($this->entryCount($firstDatabase, 'first'))->is(1);
            test($this->entryCount($firstDatabase, 'second'))->is(0);
            test($this->entryCount($secondDatabase, 'second'))->is(1);
            test($this->entryCount($secondDatabase, 'first'))->is(0);
        } finally {
            $this->clearTestingConnections();
            @unlink($firstPath);
            @unlink($secondPath);
            @unlink($firstRunnerPath);
            @unlink($secondRunnerPath);
        }
    }

    #[Test]
    public function asserts_empty_tables(Container $container): void
    {
        $this->createEntriesTable($container->get(Database::class));

        $this->database
            ->assertTableEmpty('database_tester_entries')
            ->assertTableHasCount('database_tester_entries', 0);
    }

    #[Test]
    public function failures_use_the_tempest_test_runner(Container $container): void
    {
        $this->createEntriesTable($container->get(Database::class));

        test(fn () => $this->database->assertTableHasRow('database_tester_entries', name: 'missing'))
            ->fails('Failed asserting that a row in the table `\'database_tester_entries\'` matches the given data.');

        test(fn () => $this->database->assertTableHasCount('database_tester_entries', 1))
            ->fails('Failed asserting that the table `\'database_tester_entries\'` contains `1` rows.');
    }

    #[Test]
    public function testing_database_initializer_reuses_persistent_connections_by_resolved_config(): void
    {
        $this->clearTestingConnections();

        $path = $this->temporaryDatabasePath('persistent');
        $baseConfig = new SQLiteConfig(path: $path, persistent: true, tag: 'main');
        $runnerAConfig = $this->resolveConfig($baseConfig, 'runner-a', 'main');
        $runnerBConfig = $this->resolveConfig($baseConfig, 'runner-b', 'main');
        $connection = new FakeConnection();

        try {
            $this->storeTestingConnection($runnerAConfig, $connection);

            $container = $this->initializerContainer($baseConfig, 'runner-a');
            new TestingDatabaseInitializer()->initialize(new ClassReflector(Database::class), 'main', $container);

            test($container->get(Connection::class, 'main'))->is($connection);
            test($this->connectionKey($runnerAConfig))->isNot($this->connectionKey($runnerBConfig));
        } finally {
            $this->clearTestingConnections();
            @unlink($this->runnerDatabasePath($path, 'runner-a'));
            @unlink($this->runnerDatabasePath($path, 'runner-b'));
            @unlink($path);
        }
    }

    #[Test]
    public function testing_database_initializer_does_not_reuse_connections_when_persistent_connections_are_disabled(): void
    {
        $this->clearTestingConnections();

        $baseConfig = new SQLiteConfig(path: ':memory:', persistent: false, tag: 'main');
        $connection = new FakeConnection();
        $this->storeTestingConnection($this->resolveConfig($baseConfig, 'runner-a', 'main'), $connection);

        $container = $this->initializerContainer($baseConfig, 'runner-a');
        new TestingDatabaseInitializer()->initialize(new ClassReflector(Database::class), 'main', $container);

        test($container->get(Connection::class, 'main'))->isNot($connection);
        test($container->get(Connection::class, 'main'))->instanceOf(PDOConnection::class);

        $this->clearTestingConnections();
    }

    #[Test]
    public function testing_database_initializer_reconnects_when_reused_connection_fails_ping(): void
    {
        $this->clearTestingConnections();

        $baseConfig = new SQLiteConfig(path: ':memory:', persistent: true, tag: 'main');
        $connection = new FakeConnection(pingResult: false);
        $this->storeTestingConnection($this->resolveConfig($baseConfig, 'runner-a', 'main'), $connection);

        $container = $this->initializerContainer($baseConfig, 'runner-a');
        new TestingDatabaseInitializer()->initialize(new ClassReflector(Database::class), 'main', $container);

        test($connection->reconnects)->is(1);
        test($container->get(Connection::class, 'main'))->is($connection);

        $this->clearTestingConnections();
    }

    #[Test]
    public function testing_database_initializer_registers_the_resolved_connection_with_the_original_tag(): void
    {
        $this->clearTestingConnections();

        $baseConfig = new SQLiteConfig(path: ':memory:', persistent: true, tag: 'original');
        $connection = new FakeConnection();
        $this->storeTestingConnection($this->resolveConfig($baseConfig, 'runner-a', 'original'), $connection);

        $container = $this->initializerContainer($baseConfig, 'runner-a');
        new TestingDatabaseInitializer()->initialize(new ClassReflector(Database::class), 'original', $container);

        test($container->get(Connection::class, 'original'))->is($connection);

        $this->clearTestingConnections();
    }

    #[Test]
    public function testing_database_initializer_rewrites_sqlite_file_databases_with_the_runner_name(): void
    {
        $path = sys_get_temp_dir() . '/tempest-testing.sqlite';

        $config = $this->resolveConfig(new SQLiteConfig(path: $path), 'runner-a');

        if (! $config instanceof SQLiteConfig) {
            test()->fail('SQLite config was not resolved.');
        }

        test($config->path)->is(sys_get_temp_dir() . '/tempest-testing-runner-a.sqlite');
    }

    #[Test]
    public function testing_database_initializer_does_not_rewrite_sqlite_memory_databases(): void
    {
        $config = $this->resolveConfig(new SQLiteConfig(path: ':memory:'), 'runner-a');

        if (! $config instanceof SQLiteConfig) {
            test()->fail('SQLite config was not resolved.');
        }

        test($config->path)->is(':memory:');
    }

    #[Test]
    public function testing_database_initializer_suffixes_mysql_database_names_with_the_runner_name(): void
    {
        $config = $this->resolveConfig(new MysqlConfig(database: 'app'), 'runner-a');

        if (! $config instanceof MysqlConfig) {
            test()->fail('MySQL config was not resolved.');
        }

        test($config->database)->is('app-runner-a');
    }

    #[Test]
    public function testing_database_initializer_suffixes_postgres_database_names_with_the_runner_name(): void
    {
        $config = $this->resolveConfig(new PostgresConfig(database: 'app'), 'runner-a');

        if (! $config instanceof PostgresConfig) {
            test()->fail('Postgres config was not resolved.');
        }

        test($config->database)->is('app-runner-a');
    }

    #[Test]
    public function testing_database_initializer_isolates_different_runner_names(): void
    {
        $this->clearTestingConnections();

        $path = $this->temporaryDatabasePath('isolated-runners');
        $baseConfig = new SQLiteConfig(path: $path, persistent: true, tag: 'main');
        $runnerAConfig = $this->resolveConfig($baseConfig, 'runner-a', 'main');
        $runnerBConfig = $this->resolveConfig($baseConfig, 'runner-b', 'main');
        $runnerAConnection = new FakeConnection();

        try {
            $this->storeTestingConnection($runnerAConfig, $runnerAConnection);

            $runnerBContainer = $this->initializerContainer($baseConfig, 'runner-b');
            new TestingDatabaseInitializer()->initialize(new ClassReflector(Database::class), 'main', $runnerBContainer);

            if (! $runnerAConfig instanceof SQLiteConfig || ! $runnerBConfig instanceof SQLiteConfig) {
                test()->fail('Runner configs were not resolved to SQLite configs.');
            }

            test($runnerAConfig->path)->isNot($runnerBConfig->path);
            test($this->connectionKey($runnerAConfig))->isNot($this->connectionKey($runnerBConfig));
            test($runnerBContainer->get(Connection::class, 'main'))->isNot($runnerAConnection);
            test($runnerBContainer->get(Connection::class, 'main'))->instanceOf(PDOConnection::class);
        } finally {
            $this->clearTestingConnections();
            @unlink($this->runnerDatabasePath($path, 'runner-a'));
            @unlink($this->runnerDatabasePath($path, 'runner-b'));
            @unlink($path);
        }
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

    private function entryCount(Database $database, string $name): int
    {
        $row = $database->fetchFirst(new Query(
            'SELECT COUNT(*) AS count FROM database_tester_entries WHERE name = :name',
            ['name' => $name],
        ));

        if (! is_array($row) || ! array_key_exists('count', $row)) {
            test()->fail('Could not fetch entry count.');
        }

        return (int) $row['count'];
    }

    private function initializerContainer(DatabaseConfig $config, string $runnerName): GenericContainer
    {
        $container = new GenericContainer();
        $container->config($config);
        $container->singleton(TestRunner::class, new TestRunner($runnerName, new TestEnvironment()));
        $container->singleton(EventBus::class, new NullEventBus());

        return $container;
    }

    private function resolveConfig(DatabaseConfig $config, string $runnerName, string|UnitEnum|null $tag = null): DatabaseConfig
    {
        $method = new ReflectionMethod(TestingDatabaseInitializer::class, 'resolveConfig');

        $config = $method->invoke(
            new TestingDatabaseInitializer(),
            $this->initializerContainer($config, $runnerName),
            $tag,
        );

        if (! $config instanceof DatabaseConfig) {
            test()->fail('Testing database initializer did not resolve a database config.');
        }

        return $config;
    }

    private function connectionKey(DatabaseConfig $config): string
    {
        $method = new ReflectionMethod(TestingDatabaseInitializer::class, 'getConnectionKey');

        $key = $method->invoke(new TestingDatabaseInitializer(), $config);

        if (! is_string($key)) {
            test()->fail('Testing database initializer did not resolve a connection key.');
        }

        return $key;
    }

    private function storeTestingConnection(DatabaseConfig $config, Connection $connection): void
    {
        $property = new ReflectionClass(TestingDatabaseInitializer::class)->getProperty('connections');

        $connections = $property->getValue();

        if (! is_array($connections)) {
            test()->fail('Testing database connections cache was not an array.');
        }

        $connections[$this->connectionKey($config)] = $connection;

        $property->setValue(null, $connections);
    }

    private function clearTestingConnections(): void
    {
        $property = new ReflectionClass(TestingDatabaseInitializer::class)->getProperty('connections');
        $connections = $property->getValue();

        if (! is_array($connections)) {
            test()->fail('Testing database connections cache was not an array.');
        }

        foreach ($connections as $connection) {
            if (! $connection instanceof Connection) {
                continue;
            }

            $connection->close();
        }

        $property->setValue(null, []);
    }

    private function temporaryDatabasePath(string $name): string
    {
        return sys_get_temp_dir() . "/tempest-testing-{$name}-" . uniqid() . '.sqlite';
    }

    private function runnerDatabasePath(string $path, string $runnerName): string
    {
        return str_replace(
            pathinfo($path, PATHINFO_BASENAME),
            pathinfo($path, PATHINFO_FILENAME) . "-{$runnerName}.sqlite",
            $path,
        );
    }
}

final class NullEventBus implements EventBus
{
    public function dispatch(string|object $event): void {}

    public function listen(Closure $handler, string|UnitEnum|null $event = null): void {}
}

final class FakeConnection implements Connection
{
    public int $reconnects = 0;

    public function __construct(
        private readonly bool $pingResult = true,
    ) {}

    public function beginTransaction(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return false;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollback(): bool
    {
        return true;
    }

    public function lastInsertId(): false|string
    {
        return false;
    }

    public function prepare(string $sql): PDOStatement
    {
        throw new RuntimeException('Fake connection cannot prepare statements.');
    }

    public function close(): void {}

    public function connect(): void {}

    public function reconnect(): void
    {
        $this->reconnects++;
    }

    public function ping(): bool
    {
        return $this->pingResult;
    }
}
