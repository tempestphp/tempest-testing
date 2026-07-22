<?php

namespace Tempest\Testing\Testers\Mail;

use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Mail\Mailer;
use Tempest\Testing\After;
use Tempest\Testing\Before;

trait TestsMail
{
    protected MailTester $mailer;

    private array $testsMailOriginalSingletons = [];

    #[Before]
    public function testsMailBefore(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            throw new RuntimeException('Container is not a GenericContainer, unable to test mail.');
        }

        $this->testsMailOriginalSingletons = $container->getSingletons();

        $testingMailer = new TestingMailer();

        $this->mailer = new MailTester($testingMailer);

        $container->singleton(Mailer::class, $testingMailer);
        $container->singleton(TestingMailer::class, $testingMailer);
        $container->singleton(MailTester::class, $this->mailer);
    }

    #[After]
    public function testsMailAfter(Container $container): void
    {
        if (! $container instanceof GenericContainer) {
            return;
        }

        $container->setSingletons($this->testsMailOriginalSingletons);
    }
}
