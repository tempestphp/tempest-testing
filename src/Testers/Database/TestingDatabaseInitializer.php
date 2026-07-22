<?php

declare(strict_types=1);

namespace Tempest\Testing\Testers\Database;

use Tempest\Container\Container;
use Tempest\Container\DynamicInitializer;
use Tempest\Container\Singleton;
use Tempest\Database\Config\DatabaseConfig;
use Tempest\Database\Connection\Connection;
use Tempest\Database\Connection\PDOConnection;
use Tempest\Database\Database;
use Tempest\Database\GenericDatabase;
use Tempest\Database\Transactions\GenericTransactionManager;
use Tempest\EventBus\EventBus;
use Tempest\Mapper\SerializerFactory;
use Tempest\Reflection\ClassReflector;
use Tempest\Support\Str;
use UnitEnum;

final class TestingDatabaseInitializer implements DynamicInitializer
{
    /** @var array<string, Connection> */
    private static array $connections = [];

    public function canInitialize(ClassReflector $class, string|UnitEnum|null $tag): bool
    {
        return $class->getType()->matches(Database::class);
    }

    #[Singleton]
    public function initialize(ClassReflector $class, string|UnitEnum|null $tag, Container $container): Database
    {
        $tag = Str\parse($tag) ?? 'default';

        /** @var PDOConnection|null $connection */
        $connection = self::$connections[$tag] ?? null;

        if ($connection === null) {
            $config = $container->get(DatabaseConfig::class, $tag === 'default' ? null : $tag);
            $connection = new PDOConnection($config);
            $connection->connect();

            self::$connections[$tag] = $connection;
        }

        if ($connection->ping() === false) {
            $connection->reconnect();
        }

        $container->singleton(Connection::class, $connection, $tag === 'default' ? null : $tag);

        return new GenericDatabase(
            connection: $connection,
            transactionManager: new GenericTransactionManager($connection),
            serializerFactory: $container->get(SerializerFactory::class),
            eventBus: $container->get(EventBus::class),
        );
    }
}
