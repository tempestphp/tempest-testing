<?php

namespace Tempest\Testing\Testers\View;

use Tempest\Testing\Test;
use Tempest\View\GenericView;
use Tempest\View\ViewConfig;

use function Tempest\Support\path;
use function Tempest\Testing\test;

final class ViewTesterTest
{
    use TestsViews;

    #[Test]
    public function renders_a_view_path_with_data(): void
    {
        $path = $this->createView('hello.view.php', '<p>Hello {{ $name }}</p>');

        test($this->view->render($path, name: 'Tempest'))->is('<p>Hello Tempest</p>');
    }

    #[Test]
    public function renders_a_view_instance_with_data(): void
    {
        $path = $this->createView('profile.view.php', '<span>{{ $name }}</span>');

        test($this->view->render(new GenericView($path), name: 'Brent'))->is('<span>Brent</span>');
    }

    #[Test]
    public function registers_view_components_for_rendering(ViewConfig $viewConfig): void
    {
        $path = $this->createView('component.view.php', '<x-testing-alert></x-testing-alert>');

        $this->view->registerViewComponent(
            name: 'x-testing-alert',
            html: '<strong>Registered component</strong>',
        );

        test($viewConfig->viewComponents)->hasKey('x-testing-alert');
        test($this->view->render($path))->is('<strong>Registered component</strong>');
    }

    private function createView(string $name, string $contents): string
    {
        $directory = path(sys_get_temp_dir(), 'tempest-testing-view-tester')->toString();

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        $path = path($directory, $name)->toString();
        file_put_contents($path, $contents);

        return $path;
    }
}
