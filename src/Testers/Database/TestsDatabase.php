<?php

namespace Tempest\Testing\Testers\Database;

use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Database\Connection\Connection;
use Tempest\Database\Database;
use Tempest\Database\DatabaseInitializer;
use Tempest\Testing\After;
use Tempest\Testing\Before;

trait TestsDatabase
{
    protected DatabaseTester $database;

    private array $testsDatabaseOriginalSingletons = [];

    private array $testsDatabaseOriginalDynamicInitializers = [];

    #[Before]
    public function testsDatabaseBefore(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to test database.');
        }

        $this->testsDatabaseOriginalSingletons = $container->getSingletons();
        $this->testsDatabaseOriginalDynamicInitializers = $container->getDynamicInitializers();

        $container->unregister(Database::class, tagged: true);
        $container->unregister(Connection::class, tagged: true);
        $container->removeInitializer(DatabaseInitializer::class);
        $container->addInitializer(TestingDatabaseInitializer::class);

        $this->database = new DatabaseTester($container);
    }

    #[After]
    public function testsDatabaseAfter(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            return;
        }

        $container->setSingletons($this->testsDatabaseOriginalSingletons);
        $container->setDynamicInitializers($this->testsDatabaseOriginalDynamicInitializers);
    }
}
