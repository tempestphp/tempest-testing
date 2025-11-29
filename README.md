## A standalone parallel test runner for modern PHP

This package is an experiment in rethinking testing for PHP. It's not intended for use in real-life projects. Some of the core ideas behind this package:

### A fluent testing API

```php
#[Test]
public function forget_keys_mutates_array(): void
{
    $original = [
        'foo' => 'bar',
        'baz' => 'qux',
    ];

    Arr\forget_keys($original, ['foo']);

    test($original)
        ->hasCount(1)
        ->hasKey('baz')
        ->hasNoKey('foo');
}
```

### Dependency injection support

```php
class BookTest
{
    public function __construct(
        private BookRepository $books
    ) {}
    
    #[Test]
    public function book_can_be_created(): void
    {
        $book = $this->books->create(
            title: 'Timeline Taxi',
        );
        
        test($book->id)->isNotNull();
        
        test($book->creationDate)
            ->isNotNull()
            ->equals(new DateTimeImmutable());
    }
}
```

### A simple event-driven architecture

```php

#[Singleton]
final class TestEventListeners
{
    use HasConsole;

    #[EventHandler]
    public function onTestFailed(TestFailed $event): void
    {
        $this->error(sprintf('<style="fg-red">%s</style>', $event->name));
        $this->writeln(sprintf('  <style="fg-red dim">//</style> <style="fg-red underline">%s</style>', $event->location));
        $this->writeln(sprintf('  <style="fg-red dim">//</style> <style="fg-red">%s</style>', $event->reason));
        $this->writeln();
    }
}
```

### Parallel by default

Parallel execution is the starting point instead of an afterthought.

### Clear output by default

Get immediate feedback on test failures while running them.

```console
× // Tempest\Testing\Tests\TestFoo::a
  // /Dev/tempest-testing/tests/TestFoo.php:13
  // failed asserting that true is false

× // Tempest\Testing\Tests\TestFoo::b
  // /Dev/tempest-testing/tests/TestFoo.php:20
  // failed asserting that true is false

× // Tempest\Testing\Tests\TestFoo::e
  // /Dev/tempest-testing/tests/TestFoo.php:39
  // failed asserting that true is false

 2 succeeded   3 failed   0 skipped   0.12s
```

### Compose tests however you like

```php
final class ApplicationTest
{
    use TestsEvents, TestsDatbase, TestsHttp;

    #[Test]
    public function test_before(): void
    {
        $this->events
            ->preventPropagation();
        
        $this->http
            ->post('/books', ['title' => 'Timeline Taxi'])
            ->assertRedirectTo('/books/timeline-taxi');
            
        $this->database
            ->assertContains('books', ['title' => 'Timeline Taxi']);
    }
}
```

### Tempest's no-config approach

Structure your tests however you like: in a separate dev namespaces or alongside your production code. Tempest's discovery will find them for you without any configuration on your part.