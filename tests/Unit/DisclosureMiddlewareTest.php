<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\Disclosure\AiDisclosureMiddleware;
use Padosoft\AiActCompliance\Tests\TestCase;

class DisclosureMiddlewareTest extends TestCase
{
    public function test_disclosure_header_is_added(): void
    {
        $middleware = new AiDisclosureMiddleware();

        $response = $middleware->handle(request(), fn () => response('ok'));

        self::assertTrue($response->headers->has('X-AI-Disclosure'));
    }
}
