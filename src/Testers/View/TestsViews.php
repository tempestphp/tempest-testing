<?php

namespace Tempest\Testing\Testers\View;

use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Testing\After;
use Tempest\Testing\Before;
use Tempest\View\ViewConfig;

trait TestsViews
{
    protected ViewTester $view;

    private array $testsViewsOriginalSingletons = [];

    #[Before]
    public function testsViewsBefore(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to test views.');
        }

        $this->testsViewsOriginalSingletons = $this->cloneViewConfigSingletons($container->getSingletons());

        $this->view = new ViewTester($container);
    }

    #[After]
    public function testsViewsAfter(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            return;
        }

        $container->setSingletons($this->testsViewsOriginalSingletons);
    }

    private function cloneViewConfigSingletons(array $singletons): array
    {
        foreach ($singletons as $key => $singleton) {
            if (! $singleton instanceof ViewConfig) {
                continue;
            }

            $singletons[$key] = clone $singleton;
        }

        return $singletons;
    }
}
