<?php

namespace Tempest\Testing\Testers\Mail;

use Closure;
use InvalidArgumentException;
use Symfony\Component\Mime\Address as SymfonyAddress;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Part\DataPart;
use Tempest\Mail\Attachment;
use Tempest\Mail\Email;
use Tempest\Mail\EmailAddress;
use Tempest\Mail\EmailPriority;
use Tempest\Mail\EmailToSymfonyEmailMapper;
use Tempest\Support\Arr;
use Tempest\Testing\Exceptions\TestHasFailed;
use Throwable;

use function Tempest\Mapper\map;
use function Tempest\Support\arr;
use function Tempest\Testing\test;

final class MailTester
{
    private ?SymfonyEmail $sentSymfonyEmail = null;

    public function __construct(
        private readonly TestingMailer $mailer,
    ) {}

    public function send(Email $email): self
    {
        $this->mailer->send($email);

        $sentSymfonyEmail = map($email)->with(EmailToSymfonyEmailMapper::class)->do();

        if (! $sentSymfonyEmail instanceof SymfonyEmail) {
            test()->fail('Email could not be mapped to a Symfony email.');
        }

        $this->sentSymfonyEmail = $sentSymfonyEmail;

        return $this;
    }

    /** @param class-string<Email> $email */
    public function assertSent(string $email, ?Closure $callback = null): self
    {
        $this->assertClassStringIsEmail($email);

        $sentEmail = Arr\first($this->mailer->sent, filter: fn (Email $sent) => $sent instanceof $email);

        test((bool) $sentEmail)->isTrue('Email %s was not sent.', $email);

        if ($callback instanceof Closure) {
            try {
                if ($callback($sentEmail) === false) {
                    test()->fail('The assertion callback returned `false`.');
                }
            } catch (TestHasFailed) {
                test()->fail('Email %s was sent but failed the assertion.', $email);
            }
        }

        return $this;
    }

    /** @param class-string<Email> $email */
    public function assertNotSent(string $email): self
    {
        $this->assertClassStringIsEmail($email);

        test((bool) Arr\first($this->mailer->sent, filter: fn (Email $sent) => $sent instanceof $email))
            ->isFalse('Email %s was unexpectedly sent.', $email);

        return $this;
    }

    public array $headers {
        get => $this->symfonyEmail()->getHeaders()->toArray();
    }

    public array $from {
        get => Arr\map(
            array: $this->symfonyEmail()->getFrom(),
            map: fn (SymfonyAddress $address) => new EmailAddress($address->getAddress(), $address->getName()),
        );
    }

    public array $to {
        get => Arr\map(
            array: $this->symfonyEmail()->getTo(),
            map: fn (SymfonyAddress $address) => new EmailAddress($address->getAddress(), $address->getName()),
        );
    }

    public array $attachments {
        get => Arr\map(
            array: $this->symfonyEmail()->getAttachments(),
            map: fn (DataPart $attachment) => new Attachment(
                resolve: $attachment->getBody(...),
                name: $attachment->getFilename(),
                contentType: $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
            ),
        );
    }

    public string $raw {
        get => $this->symfonyEmail()->getBody()->bodyToString();
    }

    public string $id {
        get => $this->symfonyEmail()->generateMessageId();
    }

    public function assertSubjectContains(string $expect): self
    {
        test($this->symfonyEmail()->getSubject() ?? '')->contains($expect, "Failed asserting that the email's subject is %s.", $expect);

        return $this;
    }

    public function assertSentTo(string|array $addresses): self
    {
        return $this->assertAddressListContains(
            haystack: $this->symfonyEmail()->getTo(),
            needles: $addresses,
            message: 'Failed asserting that the email was sent to [%s]. The recipients are [%s].',
        );
    }

    public function assertNotSentTo(string|array $addresses): self
    {
        return $this->assertAddressListDoesNotContain(
            haystack: $this->symfonyEmail()->getTo(),
            needles: $addresses,
            message: 'Failed asserting that the email was not sent to [%s]. The recipients are [%s].',
        );
    }

    public function assertFrom(string|array $addresses): self
    {
        return $this->assertAddressListContains(
            haystack: $this->symfonyEmail()->getFrom(),
            needles: $addresses,
            message: 'Failed asserting that the email was sent from [%s]. The senders are [%s].',
        );
    }

    public function assertNotFrom(string|array $addresses): self
    {
        return $this->assertAddressListDoesNotContain(
            haystack: $this->symfonyEmail()->getFrom(),
            needles: $addresses,
            message: 'Failed asserting that the email was not sent from [%s]. The senders are [%s].',
        );
    }

    public function assertCarbonCopy(string|array $addresses): self
    {
        return $this->assertAddressListContains(
            haystack: $this->symfonyEmail()->getCc(),
            needles: $addresses,
            message: 'Failed asserting that [%s] were included in carbon copies. The carbon copy recipients are [%s].',
        );
    }

    public function assertNotCarbonCopy(string|array $addresses): self
    {
        return $this->assertAddressListDoesNotContain(
            haystack: $this->symfonyEmail()->getCc(),
            needles: $addresses,
            message: 'Failed asserting that [%s] were not included in carbon copies. The carbonm copy recipients are [%s].',
        );
    }

    public function assertBlindCarbonCopy(string|array $addresses): self
    {
        return $this->assertAddressListContains(
            haystack: $this->symfonyEmail()->getBcc(),
            needles: $addresses,
            message: 'Failed asserting that [%s] were included in blind carbon copies. The blind carbon copy recipients are [%s].',
        );
    }

    public function assertNotBlindCarbonCopy(string|array $addresses): self
    {
        return $this->assertAddressListDoesNotContain(
            haystack: $this->symfonyEmail()->getBcc(),
            needles: $addresses,
            message: 'Failed asserting that [%s] were not included in blind carbon copies. The blind carbon copy recipients are [%s].',
        );
    }

    public function assertPriority(int|EmailPriority|null $priority): self
    {
        if ($priority instanceof EmailPriority) {
            $priority = $priority->value;
        }

        test($this->symfonyEmail()->getPriority())->is(
            $priority,
            'Failed asserting that the email has a priority of [%s]. The priority is [%s].',
            $priority,
            $this->symfonyEmail()->getPriority(),
        );

        return $this;
    }

    public function assertSee(string $expect): self
    {
        test($this->raw)->contains($expect, 'Failed asserting that the email contains %s.', $expect);

        return $this;
    }

    public function assertNotSee(string $expect): self
    {
        test($this->raw)->containsNot($expect, 'Failed asserting that the email does not contain %s.', $expect);

        return $this;
    }

    public function assertSeeInHtml(string $expect): self
    {
        $html = $this->symfonyEmail()->getHtmlBody();

        test($html)->isNotNull('The email does not contain an HTML body.');
        test($html ?? '')->contains($expect, "Failed asserting that the email's HTML contains %s.", $expect);

        return $this;
    }

    public function assertNotSeeInHtml(string $expect): self
    {
        $html = $this->symfonyEmail()->getHtmlBody();

        if ($html === null) {
            return $this;
        }

        test($html)->containsNot($expect, "Failed asserting that the email's HTML does not contain %s.", $expect);

        return $this;
    }

    public function assertSeeInText(string $expect): self
    {
        $text = $this->symfonyEmail()->getTextBody();

        test($text)->isNotNull('The email does not contain a text body.');
        test($text ?? '')->contains($expect, "Failed asserting that the email's text contains %s.", $expect);

        return $this;
    }

    public function assertNotSeeInText(string $expect): self
    {
        $text = $this->symfonyEmail()->getTextBody();

        if ($text === null) {
            return $this;
        }

        test($text)->containsNot($expect, "Failed asserting that the email's text does not contain %s.", $expect);

        return $this;
    }

    public function assertAttached(string $filename, ?Closure $callback = null): self
    {
        $attachments = $this->symfonyEmail()->getAttachments();

        test($attachments)->isNotEmpty('Failed asserting that the email has attachments.');

        foreach ($attachments as $attachment) {
            if ($attachment->getFilename() !== $filename) {
                continue;
            }

            if ($callback && $callback(new AttachmentTester($attachment)) === false) {
                test()->fail('The assertion callback returned `false` for attachment %s.', $filename);
            }

            return $this;
        }

        test()->fail(
            'Failed asserting that the email has an attachment named %s. Existing attachments: %s.',
            $filename,
            Arr\join(Arr\map($attachments, fn (DataPart $attachment) => $attachment->getName())),
        );
    }

    public function assertHasHeader(string $header, ?string $value = null): self
    {
        $headers = [];

        foreach ($this->symfonyEmail()->getHeaders()->all() as $emailHeader) {
            if (! $emailHeader instanceof HeaderInterface) {
                continue;
            }

            $headers[mb_strtolower($emailHeader->getName())] = $emailHeader;
        }

        $header = mb_strtolower($header);

        test($headers)->hasKey($header, 'Failed asserting that the email has a header %s.', $header);

        if ($value !== null) {
            test($headers[$header]->getBodyAsString())->is($value, 'Failed asserting that the email has a header %s with value %s.', $header, $value);
        }

        return $this;
    }

    public function shouldFail(?Throwable $exception = null): self
    {
        $this->mailer->shouldFail(exception: $exception);

        return $this;
    }

    /**
     * @template TEmail of Email
     * @param class-string<TEmail> $email
     * @param (Closure(TEmail, Throwable): (bool|void))|null $callback
     * @param class-string<Throwable>|string|null $exception
     */
    public function assertFailed(string $email, ?Closure $callback = null, ?string $exception = null): self
    {
        $this->assertClassStringIsEmail(email: $email);

        $failed = Arr\first($this->mailer->failed, filter: fn (FailedEmail $failed) => $failed->email instanceof $email);

        test((bool) $failed)->isTrue('Email %s did not fail.', $email);

        if (! $failed instanceof FailedEmail) {
            return $this;
        }

        if ($exception !== null) {
            if (is_a($exception, Throwable::class, allow_string: true)) {
                test($failed->exception)->instanceOf($exception, 'Email %s failed but did not throw %s.', $email, $exception);
            } else {
                test($failed->exception->getMessage())->is($exception, 'Email %s failed but threw %s.', $email, $failed->exception->getMessage());
            }
        }

        if ($callback instanceof Closure) {
            try {
                if ($callback($failed->email, $failed->exception) === false) {
                    test()->fail('The assertion callback returned `false`.');
                }
            } catch (TestHasFailed) {
                test()->fail('Email %s failed but did not match the assertion.', $email);
            }
        }

        return $this;
    }

    /** @param class-string<Email> $email */
    public function assertNotFailed(string $email): self
    {
        $this->assertClassStringIsEmail(email: $email);

        test((bool) Arr\first($this->mailer->failed, filter: fn (FailedEmail $failed) => $failed->email instanceof $email))
            ->isFalse('Email %s unexpectedly failed.', $email);

        return $this;
    }

    private function assertAddressListContains(string|array|EmailAddress|null $haystack, string|array $needles, string $message): self
    {
        $needles = Arr\wrap($needles);
        $haystack = $this->convertAddresses($haystack);

        foreach ($needles as $address) {
            test($haystack)->contains($address, $message, Arr\join($needles), Arr\join($haystack));
        }

        return $this;
    }

    private function assertAddressListDoesNotContain(string|array|EmailAddress|null $haystack, string|array $needles, string $message): self
    {
        $needles = Arr\wrap($needles);
        $haystack = $this->convertAddresses($haystack);

        foreach ($needles as $address) {
            test($haystack)->containsNot($address, $message, Arr\join($needles), Arr\join($haystack));
        }

        return $this;
    }

    private function convertAddresses(string|array|EmailAddress|null $addresses): array
    {
        return arr($addresses)
            ->map(fn (string|EmailAddress|SymfonyAddress $address) => match (true) {
                $address instanceof SymfonyAddress => $address->getAddress(),
                $address instanceof EmailAddress => $address->email,
                default => $address,
            })
            ->filter()
            ->toArray();
    }

    private function assertClassStringIsEmail(string $email): void
    {
        if (! is_a($email, Email::class, allow_string: true)) {
            throw new InvalidArgumentException(sprintf('The given email class must implement `%s`.', Email::class));
        }
    }

    private function symfonyEmail(): SymfonyEmail
    {
        if (! $this->sentSymfonyEmail instanceof SymfonyEmail) {
            test()->fail('No email has been sent through the mail tester.');
        }

        return $this->sentSymfonyEmail;
    }
}
