<?php

namespace Tempest\Testing\Testers\View;

use Tempest\Container\Container;
use Tempest\Container\Singleton;
use Tempest\View\GenericView;
use Tempest\View\View;
use Tempest\View\ViewComponent;
use Tempest\View\ViewConfig;
use Tempest\View\ViewRenderer;

#[Singleton]
final readonly class ViewTester
{
    public function __construct(
        private Container $container,
    ) {}

    public function render(string|View $view, mixed ...$params): string
    {
        if (is_string($view)) {
            $view = new GenericView($view);
        }

        $view->data(...$params);

        return $this->container->get(ViewRenderer::class)->render($view);
    }

    public function registerViewComponent(string $name, string $html, ?string $file = null, bool $isVendor = false): self
    {
        $viewComponent = new ViewComponent(
            name: $name,
            contents: $html,
            file: $file ?? $name . '.view.php',
            isVendorComponent: $isVendor,
        );

        $this->container->get(ViewConfig::class)->addViewComponent($viewComponent);

        return $this;
    }
}
