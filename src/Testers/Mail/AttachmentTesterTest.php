<?php

namespace Tempest\Testing\Testers\Mail;

use Symfony\Component\Mime\Part\DataPart;
use Tempest\Testing\Test;

use function Tempest\Testing\test;

final class AttachmentTesterTest
{
    #[Test]
    public function asserts_attachment_details(): void
    {
        $attachment = new AttachmentTester(new DataPart('body', 'test.txt', 'text/plain'));

        $attachment
            ->assertContent('Ym9keQ==')
            ->assertNamed('test.txt')
            ->assertNotNamed('other.txt')
            ->assertType('text')
            ->assertNotType('image');
    }

    #[Test]
    public function reports_attachment_assertion_failures(): void
    {
        $attachment = new AttachmentTester(new DataPart('body', 'test.txt', 'text/plain'));

        test(fn () => $attachment->assertNamed('other.txt'))
            ->fails("Failed asserting that attachment name is `'other.txt'`. Actual name is `'test.txt'`");

        test(fn () => $attachment->assertType('image'))
            ->fails("Failed asserting that attachment type is `'image'`. Actual type is `'text'`");
    }
}
