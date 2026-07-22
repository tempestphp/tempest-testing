<?php

namespace Tempest\Testing\Testers\Http;

use Psr\Http\Message\ServerRequestInterface;
use Tempest\Container\Container;
use Tempest\Http\GenericResponse;
use Tempest\Http\Method;
use Tempest\Http\Response;
use Tempest\Http\Status;
use Tempest\Router\Router;
use Tempest\Testing\Test;

final class HttpTesterTest
{
    use TestsHttp;

    #[Test]
    public function sends_requests_through_the_router(Container $container): void
    {
        $container->singleton(Router::class, new TestingRouter(new GenericResponse(
            status: Status::OK,
            body: ['name' => 'Tempest', 'meta' => ['framework' => true]],
            headers: [
                'X-Test' => 'yes',
            ],
        )));

        $this->http
            ->get('/hello', query: ['name' => 'tempest'])
            ->assertOk()
            ->assertSuccessful()
            ->assertHasHeader('x-test')
            ->assertHeaderContains('x-test', 'yes')
            ->assertJsonSubset(['name' => 'Tempest', 'meta.framework' => true])
            ->assertJsonHasKeys('name', 'meta.framework');
    }

    #[Test]
    public function asserts_response_helpers(): void
    {
        $response = new GenericResponse(Status::FOUND, 'redirecting');
        $response->addHeader('Location', '/next');

        $helper = new TestResponseHelper(
            response: $response,
            request: new \Tempest\Http\GenericRequest(Method::GET, '/', []),
        );

        $helper
            ->assertRedirect('/next')
            ->assertStatus(Status::FOUND)
            ->assertSee('redirect')
            ->assertNotSee('missing');
    }
}

final readonly class TestingRouter implements Router
{
    public function __construct(
        private Response $response,
    ) {}

    public function dispatch(\Tempest\Http\Request|ServerRequestInterface $request): Response
    {
        return $this->response;
    }
}
