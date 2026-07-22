<?php

declare(strict_types=1);

namespace Tempest\Testing\Testers\Http;

use BackedEnum;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface as PsrRequest;
use Tempest\Container\Container;
use Tempest\Http\ContentType;
use Tempest\Http\GenericRequest;
use Tempest\Http\Mappers\RequestToPsrRequestMapper;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Reflection\MethodReflector;
use Tempest\Router\Exceptions\HttpExceptionHandler;
use Tempest\Router\Route;
use Tempest\Router\RouteConfig;
use Tempest\Router\RouteDecorator;
use Tempest\Router\Router;
use Tempest\Router\Routing\Construction\DiscoveredRoute;
use Tempest\Router\Routing\Construction\RouteConfigurator;
use Tempest\Router\SecFetchMode;
use Tempest\Router\SecFetchSite;
use Tempest\Router\Static\StaticPageConfig;
use Tempest\Router\StaticPage;
use Tempest\Support\Uri;
use Throwable;

use function Tempest\Mapper\map;

final class HttpTester
{
    private(set) ?ContentType $contentType = null;

    private(set) bool $includeSecFetchHeaders = true;

    private(set) bool $throwExceptions = false;

    public function __construct(
        private Container $container,
    ) {}

    public function throwExceptions(bool $throwExceptions = true): self
    {
        $this->throwExceptions = $throwExceptions;

        return $this;
    }

    /** @param array{0: class-string, 1: string}|class-string|MethodReflector $action */
    public function registerRoute(array|string|MethodReflector $action): self
    {
        $reflector = match (true) {
            $action instanceof MethodReflector => $action,
            is_array($action) => MethodReflector::fromParts(...$action),
            default => MethodReflector::fromParts($action, '__invoke'),
        };

        $route = $reflector->getAttribute(Route::class);

        if ($route === null) {
            throw new InvalidArgumentException('Missing route attribute');
        }

        $configurator = $this->container->get(RouteConfigurator::class);

        $configurator->addRoute(
            DiscoveredRoute::fromRoute(
                $route,
                [
                    ...$reflector->getDeclaringClass()->getAttributes(RouteDecorator::class),
                    ...$reflector->getAttributes(RouteDecorator::class),
                ],
                $reflector,
            ),
        );

        $routeConfig = $this->container->get(RouteConfig::class);
        $routeConfig->apply($configurator->toRouteConfig());

        return $this;
    }

    /** @param array{0: class-string, 1: string}|class-string|MethodReflector $action */
    public function registerStaticPage(array|string|MethodReflector $action): self
    {
        $reflector = match (true) {
            $action instanceof MethodReflector => $action,
            is_array($action) => MethodReflector::fromParts(...$action),
            default => MethodReflector::fromParts($action, '__invoke'),
        };

        $staticPage = $reflector->getAttribute(StaticPage::class);

        if ($staticPage === null) {
            throw new InvalidArgumentException('Missing static page attribute');
        }

        $this->container->get(StaticPageConfig::class)->addHandler(
            $staticPage,
            $reflector,
        );

        return $this;
    }

    public function as(ContentType $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function withoutSecFetchHeaders(): self
    {
        $this->includeSecFetchHeaders = false;

        return $this;
    }

    public function get(string $uri, array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest(new GenericRequest(
            method: Method::GET,
            uri: Uri\merge_query($uri, ...$query),
            body: [],
            headers: $this->createHeaders($headers),
        ));
    }

    public function head(string $uri, array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest(new GenericRequest(Method::HEAD, Uri\merge_query($uri, ...$query), [], $this->createHeaders($headers)));
    }

    public function query(string $uri, array|string $body = [], array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest($this->request(Method::QUERY, $uri, $body, $query, $headers));
    }

    public function post(string $uri, array|string $body = [], array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest($this->request(Method::POST, $uri, $body, $query, $headers));
    }

    public function put(string $uri, array|string $body = [], array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest($this->request(Method::PUT, $uri, $body, $query, $headers));
    }

    public function delete(string $uri, array|string $body = [], array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest($this->request(Method::DELETE, $uri, $body, $query, $headers));
    }

    public function connect(string $uri, array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest(new GenericRequest(Method::CONNECT, Uri\merge_query($uri, ...$query), [], $this->createHeaders($headers)));
    }

    public function options(string $uri, array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest(new GenericRequest(Method::OPTIONS, Uri\merge_query($uri, ...$query), [], $this->createHeaders($headers)));
    }

    public function trace(string $uri, array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest(new GenericRequest(Method::TRACE, Uri\merge_query($uri, ...$query), [], $this->createHeaders($headers)));
    }

    public function patch(string $uri, array|string $body = [], array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->sendRequest($this->request(Method::PATCH, $uri, $body, $query, $headers));
    }

    public function sendRequest(Request $request): TestResponseHelper
    {
        $router = $this->container->get(Router::class);
        $psrRequest = map($request)->with(RequestToPsrRequestMapper::class)->do();

        if ($this->throwExceptions) {
            $response = $router->dispatch($psrRequest);
        } else {
            try {
                $response = $router->dispatch($psrRequest);
            } catch (Throwable $throwable) {
                return new TestResponseHelper(
                    response: $this->container->get(HttpExceptionHandler::class)->renderResponse($request, $throwable),
                    request: $request,
                    container: $this->container,
                    throwable: $throwable,
                );
            }
        }

        return new TestResponseHelper($response, $request, $this->container);
    }

    public function makePsrRequest(
        string $uri,
        Method $method = Method::GET,
        array|string $body = [],
        array $headers = [],
        array $cookies = [],
        array $files = [],
    ): PsrRequest {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method->value;

        foreach ($this->createHeaders($headers) as $key => $value) {
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            $key = strtoupper(str_replace('-', '_', (string) $key));

            $_SERVER["HTTP_{$key}"] = $value;
        }

        $_COOKIE = $cookies;
        $_POST = is_array($body) ? $body : [];

        return ServerRequestFactory::fromGlobals()->withUploadedFiles($files);
    }

    private function request(Method $method, string $uri, array|string $body, array $query, array $headers): Request
    {
        return new GenericRequest(
            method: $method,
            uri: Uri\merge_query($uri, ...$query),
            body: is_string($body) ? [] : $body,
            headers: $this->createHeaders($headers),
            raw: is_string($body) ? $body : null,
        );
    }

    private function createHeaders(array $headers = []): array
    {
        $headers = $this->normalizeHeaders($headers);

        $key = array_find_key(
            array: $headers,
            callback: fn (mixed $_, int|string $headerKey): bool => strcasecmp((string) $headerKey, 'accept') === 0,
        );

        if ($this->contentType instanceof ContentType) {
            $headers[$key ?? 'accept'] = $this->contentType->value;
        }

        if ($this->includeSecFetchHeaders) {
            if (! array_key_exists('sec-fetch-site', array_change_key_case($headers, case: CASE_LOWER))) {
                $headers['sec-fetch-site'] = SecFetchSite::SAME_ORIGIN;
            }

            if (! array_key_exists('sec-fetch-mode', array_change_key_case($headers, case: CASE_LOWER))) {
                $headers['sec-fetch-mode'] = SecFetchMode::CORS;
            }
        }

        return $headers;
    }

    /** @return array<string, mixed> */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
