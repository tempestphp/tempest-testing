<?php

namespace Tempest\Testing\Testers\Mail;

use Symfony\Component\Mime\Part\DataPart;

use function Tempest\Testing\test;

final class AttachmentTester
{
    public array $headers {
        get => $this->original->getHeaders()->toArray();
    }

    public string $body {
        get => $this->original->bodyToString();
    }

    public string $name {
        get => $this->original->getFilename() ?? '';
    }

    public string $mediaType {
        get => $this->original->getMediaType();
    }

    public function __construct(
        private readonly DataPart $original,
    ) {}

    public function assertContent(string $expected): self
    {
        test($this->body)->is($expected, 'Failed asserting that attachment content is %s. Actual content is %s', $expected, $this->body);

        return $this;
    }

    public function assertNamed(string $name): self
    {
        test($this->name)->is($name, 'Failed asserting that attachment name is %s. Actual name is %s', $name, $this->name);

        return $this;
    }

    public function assertNotNamed(string $name): self
    {
        test($this->name)->isNot($name, 'Failed asserting that attachment name is not %s.', $name);

        return $this;
    }

    public function assertType(string $type): self
    {
        test($this->mediaType)->is($type, 'Failed asserting that attachment type is %s. Actual type is %s', $type, $this->mediaType);

        return $this;
    }

    public function assertNotType(string $type): self
    {
        test($this->mediaType)->isNot($type, 'Failed asserting that attachment type is not %s.', $type);

        return $this;
    }
}
