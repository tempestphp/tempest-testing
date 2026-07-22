<?php

namespace Tempest\Testing\Testers\Http;

use Tempest\Http\Response;
use Tempest\Router\ResponseSender;

final class TestingResponseSender implements ResponseSender
{
    /** @var Response[] */
    private(set) array $responses = [];

    public function send(Response $response): Response
    {
        $this->responses[] = $response;

        return $response;
    }
}
