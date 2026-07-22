<?php

declare(strict_types=1);

namespace Tempest\Testing\Testers\Http;

use Closure;
use Generator;
use JsonSerializable;
use Tempest\Container\Container;
use Tempest\Cryptography\Encryption\Encrypter;
use Tempest\Http\Cookie\Cookie;
use Tempest\Http\Header;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Session\FormSession;
use Tempest\Http\Session\Session;
use Tempest\Http\Status;
use Tempest\Support\Arr;
use Tempest\Support\Json;
use Tempest\Validation\Rule;
use Tempest\View\View;
use Tempest\View\ViewRenderer;
use Throwable;

use function Tempest\Support\arr;
use function Tempest\Testing\test;

final class TestResponseHelper
{
    public function __construct(
        private(set) Response $response,
        private(set) Request $request,
        private ?Container $container = null,
        private(set) ?Throwable $throwable = null,
    ) {}

    public Status $status {
        get => $this->response->status;
    }

    /** @var Header[] */
    public array $headers {
        get => $this->response->headers;
    }

    public View|string|array|Generator|JsonSerializable|null $body {
        get => $this->response->body;
    }

    public function assertHasHeader(string $name): self
    {
        test($this->normalizedHeaders())
            ->hasKey(mb_strtolower($name), 'Failed to assert that response contains the header [%s].', $name);

        return $this;
    }

    public function assertDoesNotHaveHeader(string $name): self
    {
        test($this->normalizedHeaders())
            ->missesKey(mb_strtolower($name), 'Failed to assert that response does not contain the header [%s].', $name);

        return $this;
    }

    public function assertHeaderContains(string $name, mixed $value): self
    {
        $header = $this->header($name);

        test($header->values)->contains($value, 'Failed to assert that response header [%s] value contains [%s].', $name, $value);

        return $this;
    }

    public function assertHeaderMatches(string $name, string $format): self
    {
        $header = $this->header($name);

        foreach ($header->values as $value) {
            if (is_scalar($value) && $this->stringMatchesFormat($format, (string) $value)) {
                return $this;
            }
        }

        test()->fail('Failed to assert that response header [%s] value contains [%s].', $name, $format);
    }

    public function assertRedirect(?string $to = null): self
    {
        test($this->status->isRedirect())->isTrue('Failed asserting that status [%s] is a redirect.', $this->status->value);

        return $to === null
            ? $this->assertHasHeader('Location')
            : $this->assertHeaderContains('Location', $to);
    }

    public function assertOk(): self
    {
        return $this->assertStatus(Status::OK);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(Status::FORBIDDEN);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(Status::NOT_FOUND);
    }

    public function assertSuccessful(): self
    {
        test($this->status->isSuccessful())->isTrue();

        return $this;
    }

    public function assertClientError(): self
    {
        test($this->status->isClientError())->isTrue();

        return $this;
    }

    public function assertServerError(): self
    {
        test($this->status->isServerError())->isTrue();

        return $this;
    }

    public function assertStatus(Status $expected): self
    {
        test($this->status)->is($expected, 'Failed asserting status [%s] matched expected status of [%s].', $this->status->value, $expected->value);

        return $this;
    }

    public function assertHasCookie(string $key, string|Closure|null $value = null): self
    {
        $cookies = $this->cookies();

        test($cookies)->hasKey($key, 'No cookie was set for [%s], available cookies: %s', $key, implode(', ', array_keys($cookies)));

        $encrypter = $this->container()->get(Encrypter::class);
        $cookie = $cookies[$key]->value ? $encrypter->decrypt($cookies[$key]->value) : '';

        if ($value instanceof Closure) {
            $value($cookie);
        }

        if (is_string($value)) {
            test($cookie)->isEqualTo($value);
        }

        return $this;
    }

    public function assertDoesNotHaveCookie(string $key, string|Closure|null $value = null): self
    {
        test($this->cookies())->missesKey($key, "A cookie was set for [%s], while it shouldn't have been", $key);

        return $this;
    }

    public function assertHasForm(Closure $closure): self
    {
        if (false === $closure($this->container()->get(FormSession::class))) {
            test()->fail('Failed validating form session.');
        }

        return $this;
    }

    public function assertHasFormOriginalValues(array $values): self
    {
        $originalValues = $this->container()->get(FormSession::class)->values();

        foreach ($values as $key => $expectedValue) {
            test($originalValues)->hasKey($key, 'No original form value was set for [%s], available original form values: %s', $key, implode(', ', array_keys($originalValues)));
            test($originalValues[$key])->isEqualTo($expectedValue, 'Original form value for [%s] does not match expected value.', $key);
        }

        return $this;
    }

    public function assertHasSession(string $key, ?Closure $callback = null): self
    {
        $session = $this->container()->get(Session::class);
        $data = $session->get($key);

        test($data)->isNotNull('No session value was set for [%s], available session keys: %s', $key, implode(', ', array_keys($session->data)));

        if ($callback instanceof Closure) {
            $callback($session, $data);
        }

        return $this;
    }

    public function assertHasValidationError(string $key, ?Closure $callback = null): self
    {
        $validationErrors = $this->validationErrors();

        test($validationErrors)->hasKey($key, 'No validation error was set for [%s], available validation errors: %s', $key, implode(', ', array_keys($validationErrors)));

        if ($callback instanceof Closure) {
            $callback($validationErrors);
        }

        return $this;
    }

    public function assertHasNoValidationsErrors(): self
    {
        $validationErrors = $this->container()->get(FormSession::class)->getErrors();

        test($validationErrors)->isEmpty(
            arr($validationErrors)
                ->map(fn (mixed $failingRules, int|string $key) => (string) $key . ': ' . (string) arr($failingRules)->map(fn (mixed $rule) => $rule instanceof Rule
                    ? $rule::class
                    : (string) $rule)->implode(', '))
                ->implode(', ')
                ->prepend('There should be no validation errors, but there were: ')
                ->toString(),
        );

        return $this;
    }

    public function assertSee(string $search): self
    {
        test($this->bodyAsString())->contains($search);

        return $this;
    }

    public function assertNotSee(string $search): self
    {
        test($this->bodyAsString())->containsNot($search);

        return $this;
    }

    public function assertViewData(string $key, ?Closure $callback = null): self
    {
        $data = $this->viewBody()->data;

        test($data)->hasKey($key, 'No view data was set for [%s], available view data keys: %s', $key, implode(', ', array_keys($data)));

        if ($callback instanceof Closure && $callback($data, $data[$key]) === false) {
            test()->fail('Failed validating view data for [%s]', $key);
        }

        return $this;
    }

    public function assertViewDataMissing(string $key): self
    {
        test($this->viewBody()->data)->missesKey($key, 'Failed asserting that view data key [%s] was not set', $key);

        return $this;
    }

    public function assertViewDataAll(Closure $callback): self
    {
        if ($callback($this->viewBody()->data) === false) {
            test()->fail('Failed validating all view data');
        }

        return $this;
    }

    public function assertView(string $view): self
    {
        test($this->viewBody()->path)->isEqualTo($view);

        return $this;
    }

    /** @param class-string<View> $expected */
    public function assertViewModel(string $expected, ?Closure $callback = null): self
    {
        test($this->body)->instanceOf($expected);

        if ($callback instanceof Closure && $callback($this->body) === false) {
            test()->fail('Failed validating view model');
        }

        return $this;
    }

    public function assertJson(array $expected = []): self
    {
        test($this->response->body)->isEqualTo(arr($expected)->undot()->toArray());

        return $this;
    }

    public function assertJsonSubset(array $expected): self
    {
        $expected = arr($expected)->undot()->dot()->toArray();
        $actual = arr($this->response->body)->dot()->toArray();

        foreach ($expected as $key => $value) {
            test($actual)->hasKey($key);
            test($actual[$key])->isEqualTo($value);
        }

        return $this;
    }

    public function assertJsonHasKeys(string ...$keys): self
    {
        $actual = arr($this->response->body)->dot()->toArray();

        foreach ($keys as $key) {
            test($actual)->hasKey($key);
        }

        return $this;
    }

    public function assertJsonContains(array $expected): self
    {
        foreach (arr($expected)->undot() as $key => $value) {
            test($this->arrayBody())->hasKey($key);
            test($this->arrayBody()[$key])->isEqualTo($value);
        }

        return $this;
    }

    public function assertHasJsonValidationErrors(array $expectedErrors): self
    {
        test([Status::BAD_REQUEST, Status::FOUND, Status::UNPROCESSABLE_CONTENT])->contains($this->response->status);

        $validationErrors = Arr\dot($this->validationErrors());

        foreach (Arr\dot($expectedErrors) as $key => $expectedMessage) {
            test($validationErrors)->hasKey($key);
            test($this->stringMatchesFormat($expectedMessage, $validationErrors[$key]))->isTrue();
        }

        return $this;
    }

    public function assertHasNoJsonValidationErrors(): self
    {
        test([Status::BAD_REQUEST, Status::FOUND])->containsNot($this->response->status);
        test($this->response->getHeader('x-validation'))->isNull();

        return $this;
    }

    /** @mago-expect lint:no-debug-symbols */
    public function dd(): never
    {
        if ($this->throwable instanceof Throwable) {
            dump(sprintf('There was a [%s] exception during this request handling: %s', $this->throwable::class, $this->throwable->getMessage()));
        }

        dd($this->response);
    }

    private function header(string $name): Header
    {
        $header = $this->response->getHeader($name);

        if (! $header instanceof Header) {
            test()->fail('Failed to assert that response contains the header [%s].', $name);
        }

        return $header;
    }

    private function normalizedHeaders(): array
    {
        $headers = [];

        foreach ($this->response->headers as $name => $header) {
            $headers[mb_strtolower((string) $name)] = $header;
        }

        return $headers;
    }

    private function container(): Container
    {
        if (! $this->container instanceof Container) {
            test()->fail('This assertion requires a container.');
        }

        return $this->container;
    }

    /** @return array<string, Cookie> */
    private function cookies(): array
    {
        $header = $this->response->getHeader('set-cookie');
        $cookies = [];

        foreach ($header->values ?? [] as $cookie) {
            if (! is_string($cookie)) {
                continue;
            }

            $cookie = Cookie::createFromString($cookie);
            $cookies[$cookie->key] = $cookie;
        }

        return $cookies;
    }

    private function bodyAsString(): string
    {
        $body = $this->body;

        if ($body instanceof View) {
            /** @var View $body */
            return $this->container()->get(ViewRenderer::class)->render($body);
        }

        if (is_array($body)) {
            return json_encode($body) ?: '';
        }

        return is_string($body) ? $body : '';
    }

    private function viewBody(): View
    {
        $body = $this->body;

        if (! $body instanceof View) {
            test()->fail('Response is not a %s', View::class);
        }

        /** @var View $body */
        return $body;
    }

    private function arrayBody(): array
    {
        if (! is_array($this->response->body)) {
            test()->fail('Response body is not an array.');
        }

        return $this->response->body;
    }

    private function validationErrors(): array
    {
        $validationErrors = $this->response->getHeader('x-validation')?->first();

        test($validationErrors)->isNotNull('The response does not have a x-validation header.');

        if (! is_string($validationErrors)) {
            return [];
        }

        $decoded = Json\decode($validationErrors);

        return is_array($decoded) ? $decoded : [];
    }

    private function stringMatchesFormat(string $format, string $value): bool
    {
        $pattern = preg_quote($format, '/');
        $pattern = str_replace(['%s', '%d'], ['.*', '\d+'], $pattern);

        return preg_match('/^' . $pattern . '$/s', $value) === 1;
    }
}
