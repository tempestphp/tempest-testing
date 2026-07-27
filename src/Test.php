<?php

namespace Tempest\Testing;

use Attribute;
use ReflectionMethod;
use Tempest\Reflection\MethodReflector;

#[Attribute(Attribute::TARGET_METHOD)]
final class Test
{
    public MethodReflector $handler;

    /** @var MethodReflector[] */
    public array $before = [];

    /** @var MethodReflector[] */
    public array $after = [];

    /** @var array<array-key, mixed>|null */
    public ?array $provide = null;

    public string $name {
        get => $this->handler->getDeclaringClass()->getName() . '::' . $this->handler->getName();
    }

    public string $location {
        get {
            $line = $this->handler->getReflection()->getStartLine();
            $fileName = $this->handler->getReflection()->getFileName() ?: $this->handler->getDeclaringClass()->getName();

            return $line
                ? "{$fileName}:{$line}"
                : $fileName;
        }
    }

    public static function fromName(string $name): self
    {
        $reflector = new MethodReflector(new ReflectionMethod(...explode('::', $name)));

        return self::fromReflector($reflector);
    }

    public static function fromReflector(MethodReflector $reflector): self
    {
        $self = new self();

        $self->handler = $reflector;

        $after = [];

        foreach ($reflector->getDeclaringClass()->getPublicMethods() as $method) {
            if ($method->hasAttribute(Before::class)) {
                $self->before[] = $method;
            }

            if ($method->hasAttribute(After::class)) {
                $after[] = $method;
            }
        }

        $self->after = array_reverse($after);

        $self->provide = $reflector->getAttribute(Provide::class)?->entries;

        return $self;
    }

    public function matchesFilter(string $filter): bool
    {
        return str_contains($this->name, $filter) || str_contains(str_replace('\\', '', $this->name), $filter);
    }
}
