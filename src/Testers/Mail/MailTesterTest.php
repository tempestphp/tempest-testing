<?php

namespace Tempest\Testing\Testers\Mail;

use RuntimeException;
use Tempest\Mail\Attachment;
use Tempest\Mail\Email;
use Tempest\Mail\EmailPriority;
use Tempest\Mail\Envelope;
use Tempest\Mail\GenericEmail;
use Tempest\Mail\Mailer;
use Tempest\Testing\Test;

use function Tempest\Testing\test;

final class MailTesterTest
{
    use TestsMail;

    #[Test]
    public function asserts_sent_mail(Mailer $mail): void
    {
        $email = $this->email();

        $mail->send($email);

        $this->mailer
            ->assertSent(GenericEmail::class)
            ->assertNotSent(UnsentEmail::class);
    }

    #[Test]
    public function asserts_sent_mail_details(): void
    {
        $this->mailer->send($this->email());

        $this->mailer
            ->assertSubjectContains('Greetings')
            ->assertSentTo('to@example.com')
            ->assertNotSentTo('missing@example.com')
            ->assertFrom('from@example.com')
            ->assertNotFrom('missing@example.com')
            ->assertCarbonCopy('cc@example.com')
            ->assertBlindCarbonCopy('bcc@example.com')
            ->assertPriority(EmailPriority::HIGH)
            ->assertSee('Hello')
            ->assertSeeInHtml('Hello')
            ->assertSeeInText('Plain')
            ->assertHasHeader('X-Test', 'yes')
            ->assertAttached('test.txt', fn (AttachmentTester $attachment) => $attachment->assertNamed('test.txt')->assertType('text'));
    }

    #[Test]
    public function asserts_failed_mail(Mailer $mail): void
    {
        $this->mailer->shouldFail(new RuntimeException('Transport broke'));

        test(fn () => $mail->send($this->email()))->exceptionThrown(RuntimeException::class);

        $this->mailer
            ->assertFailed(GenericEmail::class, exception: RuntimeException::class)
            ->assertFailed(GenericEmail::class, exception: 'Transport broke')
            ->assertNotFailed(UnsentEmail::class);
    }

    private function email(): GenericEmail
    {
        return new GenericEmail(
            subject: 'Greetings from Tempest',
            to: 'to@example.com',
            html: '<strong>Hello</strong>',
            text: 'Plain Hello',
            from: 'from@example.com',
            cc: 'cc@example.com',
            bcc: 'bcc@example.com',
            headers: ['X-Test' => 'yes'],
            priority: EmailPriority::HIGH,
            attachments: [
                new Attachment(
                    resolve: fn () => 'attachment-body',
                    name: 'test.txt',
                    contentType: 'text/plain',
                ),
            ],
        );
    }
}

final class UnsentEmail implements Email
{
    public Envelope $envelope {
        get => new Envelope(subject: null, to: 'nobody@example.com');
    }

    public string $html {
        get => '';
    }
}
