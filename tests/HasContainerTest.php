<?php

namespace Tempest\Testing\Tests;

use Tempest\Container\Container;
use Tempest\Testing\Test;
use Tempest\Testing\Testers\HasContainer;

use function Tempest\Testing\test;

final class HasContainerTest
{
    use HasContainer;

    #[Test]
    public function sets_the_protected_container_property_before_each_test(Container $container): void
    {
        test(isset($this->container))->is(true);
        test($this->container)->is($container);
    }

    #[Test]
    public function testers_using_has_container_can_access_the_child_process_container(Container $container): void
    {
        $tester = new HasContainerFixtureTester($this->container);

        test($tester->container())->is($container);
    }
}

final readonly class HasContainerFixtureTester
{
    public function __construct(
        private Container $container,
    ) {}

    public function container(): Container
    {
        return $this->container;
    }
}
