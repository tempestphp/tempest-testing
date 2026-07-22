<?php

namespace Tempest\Testing\Testers\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Tempest\EventBus\EventBus;
use Tempest\Mail\Email;
use Tempest\Mail\EmailSendingFailed;
use Tempest\Mail\EmailWasSent;
use Tempest\Mail\Mailer;
use Throwable;

use function Tempest\Container\get;

final class TestingMailer implements Mailer
{
    private ?EventBus $eventBus {
        get => get(className: EventBus::class);
    }

    /** @var Email[] */
    private(set) array $sent = [];

    /** @var FailedEmail[] */
    private(set) array $failed = [];

    private(set) ?Throwable $exception = null;

    public function send(Email $email): void
    {
        if ($this->exception instanceof Throwable) {
            $failure = new FailedEmail(
                email: $email,
                exception: $this->exception,
            );

            $this->failed[] = $failure;
            $this->exception = null;

            $this->eventBus?->dispatch(event: new EmailSendingFailed(
                email: $email,
                exception: $failure->exception,
            ));

            throw $failure->exception;
        }

        $this->sent[] = $email;

        $this->eventBus?->dispatch(event: new EmailWasSent(email: $email));
    }

    public function shouldFail(?Throwable $exception = null): void
    {
        $this->exception = $exception ?? new TransportException(message: 'Test transport failure');
    }
}
