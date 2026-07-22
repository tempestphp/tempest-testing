<?php

namespace Tempest\Testing\Testers\Console;

use Closure;
use Fiber;
use Tempest\Console\Actions\ExecuteConsoleCommand;
use Tempest\Console\Components\InteractiveComponentRenderer;
use Tempest\Console\Console;
use Tempest\Console\ExitCode;
use Tempest\Console\GenericConsole;
use Tempest\Console\Input\ConsoleArgumentBag;
use Tempest\Console\Input\MemoryInputBuffer;
use Tempest\Console\InputBuffer;
use Tempest\Console\Key;
use Tempest\Console\Output\MemoryOutputBuffer;
use Tempest\Console\OutputBuffer;
use Tempest\Container\Container;
use Tempest\Highlight\Highlighter;

use function Tempest\Testing\test;

final class ConsoleTester
{
    private (OutputBuffer&MemoryOutputBuffer)|null $output = null;

    private (InputBuffer&MemoryInputBuffer)|null $input = null;

    private ?InteractiveComponentRenderer $componentRenderer = null;

    private ?ExitCode $exitCode = null;

    private bool $withPrompting = true;

    private (Console&GenericConsole)|null $console = null;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function call(string|Closure|array $command, string|array $arguments = []): self
    {
        $clone = clone $this;

        $this->output ??= new MemoryOutputBuffer();
        $this->output->clear();
        $memoryOutputBuffer = $this->output;
        $clone->container->singleton(OutputBuffer::class, $memoryOutputBuffer);

        $this->input ??= new MemoryInputBuffer();
        $this->input->clear();
        $memoryInputBuffer = $this->input;
        $clone->container->singleton(InputBuffer::class, $memoryInputBuffer);

        $this->console ??= new GenericConsole(
            output: $memoryOutputBuffer,
            input: $memoryInputBuffer,
            highlighter: $clone->container->get(Highlighter::class, 'console'),
            executeConsoleCommand: $clone->container->get(ExecuteConsoleCommand::class),
            argumentBag: $clone->container->get(ConsoleArgumentBag::class),
        );

        $console = $this->console;

        if ($this->withPrompting === false) {
            $console->disablePrompting();
        }

        if ($this->componentRenderer !== null) {
            $console->setComponentRenderer($this->componentRenderer);
        }

        $clone->container->singleton(Console::class, $console);

        $clone->output = $memoryOutputBuffer;
        $clone->input = $memoryInputBuffer;

        if ($command instanceof Closure) {
            $fiber = new Fiber(function () use ($clone, $command, $console): void {
                $exitCode = $command($console) ?? ExitCode::SUCCESS;
                $clone->exitCode = $exitCode instanceof ExitCode ? $exitCode : ExitCode::SUCCESS;
            });
        } else {
            $fiber = new Fiber(function () use ($command, $arguments, $clone): void {
                $clone->container->singleton(ConsoleArgumentBag::class, new ConsoleArgumentBag(['tempest']));
                $exitCode = $this->container->invoke(
                    ExecuteConsoleCommand::class,
                    command: $command,
                    arguments: $arguments,
                );
                $clone->exitCode = $exitCode instanceof ExitCode ? $exitCode : ExitCode::SUCCESS;
            });
        }

        $fiber->start();

        if ($clone->componentRenderer !== null) {
            $clone->input("\e[1;1R"); // Set cursor for interactive testing
        }

        return $clone;
    }

    public function complete(?string $command = null): self
    {
        if ($command) {
            $input = explode(' ', $command);

            $inputString = implode(' ', array_map(
                fn (string $item) => "--input=\"{$item}\"",
                $input,
            ));
        } else {
            $inputString = '';
        }

        return $this->call("_complete --current=0 --input=\"./tempest\" {$inputString}");
    }

    public function input(int|string|Key $input): self
    {
        $this->outputBuffer()->clear();

        $this->inputBuffer()->add($input);

        return $this;
    }

    public function submit(int|string $input = ''): self
    {
        $input = (string) $input;

        $this->input($input . Key::ENTER->value);

        return $this;
    }

    public function confirm(): self
    {
        return $this->submit('yes');
    }

    public function deny(): self
    {
        return $this->submit('no');
    }

    public function print(): self
    {
        echo 'OUTPUT:' . PHP_EOL;
        echo $this->outputBuffer()->asUnformattedString();

        return $this;
    }

    public function printFormatted(): self
    {
        echo $this->outputBuffer()->asFormattedString();

        return $this;
    }

    public function getBuffer(?callable $callback = null): array
    {
        $buffer = array_map('trim', $this->outputBuffer()->getBufferWithoutFormatting());

        $this->outputBuffer()->clear();

        if ($callback !== null) {
            $result = $callback($buffer);

            return is_array($result) ? $result : [$result];
        }

        return $buffer;
    }

    public function useInteractiveTerminal(): self
    {
        $this->componentRenderer = $this->container->get(InteractiveComponentRenderer::class);

        return $this;
    }

    public function assertSee(string $text): self
    {
        return $this->contains($text);
    }

    public function assertSeeCount(string $text, int $expectedCount): self
    {
        $actualCount = substr_count($this->outputBuffer()->asUnformattedString(), $text);

        test($actualCount)->is(
            $expectedCount,
            'Failed to assert that console output counted: %s exactly %s times. These lines were printed: %s',
            $text,
            $expectedCount,
            PHP_EOL . PHP_EOL . $this->outputBuffer()->asUnformattedString() . PHP_EOL,
        );

        return $this;
    }

    public function assertNotSee(string $text): self
    {
        return $this->containsNot($text);
    }

    public function contains(string $text): self
    {
        test($this->outputBuffer()->asUnformattedString())
            ->contains($text, 'console output did not contain: %s', $text);

        return $this;
    }

    public function containsNot(string $text): self
    {
        test($this->outputBuffer()->asUnformattedString())
            ->containsNot($text, "console output contained %s while it shouldn't", $text);

        return $this;
    }

    public function assertContainsFormattedText(string $text): self
    {
        test($this->outputBuffer()->asFormattedString())
            ->contains(
                $text,
                'Failed to assert that console output included formatted text: %s. These lines were printed: %s',
                $text,
                PHP_EOL . $this->outputBuffer()->asFormattedString(),
            );

        return $this;
    }

    public function isJson(): self
    {
        test($this->outputBuffer()->asUnformattedString())->isJson();

        return $this;
    }

    public function hasExitCode(ExitCode $exitCode): self
    {
        test($this->exitCode)
            ->isNotNull()
            ->is($exitCode, 'console exit code did not match expected %s, instead got %s', $exitCode, $this->exitCode);

        return $this;
    }

    public function succeeds(): self
    {
        $this->hasExitCode(ExitCode::SUCCESS);

        return $this;
    }

    public function fails(): self
    {
        $this->hasExitCode(ExitCode::ERROR);

        return $this;
    }

    public function assertCancelled(): self
    {
        $this->hasExitCode(ExitCode::CANCELLED);

        return $this;
    }

    public function assertInvalid(): self
    {
        $this->hasExitCode(ExitCode::INVALID);

        return $this;
    }

    public function withoutPrompting(): self
    {
        $this->withPrompting = false;

        return $this;
    }

    public function withPrompting(): self
    {
        $this->withPrompting = true;

        return $this;
    }

    public function dd(): self
    {
        ld($this->outputBuffer()->asUnformattedString());

        return $this; // @mago-expect analysis:unevaluated-code
    }

    private function outputBuffer(): OutputBuffer&MemoryOutputBuffer
    {
        if ($this->output === null) {
            $this->output = new MemoryOutputBuffer();
        }

        return $this->output;
    }

    private function inputBuffer(): InputBuffer&MemoryInputBuffer
    {
        if ($this->input === null) {
            $this->input = new MemoryInputBuffer();
        }

        return $this->input;
    }
}
