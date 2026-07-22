<?php

namespace Tempest\Testing\Testers\Mail;

use Tempest\Mail\Email;
use Throwable;

final readonly class FailedEmail
{
    public function __construct(
        public Email $email,
        public Throwable $exception,
    ) {}
}
