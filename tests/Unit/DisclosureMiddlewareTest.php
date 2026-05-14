<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Illuminate\Http\Request;
use Padosoft\AiActCompliance\Disclosure\AiDisclosureMiddleware;
use Padosoft\AiActCompliance\Tests\TestCase;

class DisclosureMiddlewareTest extends TestCase
{
    public function test_disclosure_header_is_added(): void
    {
        $middleware = new AiDisclosureMiddleware();

        $response = $middleware->handle(request(), fn () => response('ok'));

        self::assertSame(
            'This response may include AI-generated content.',
            $response->headers->get('X-AI-Disclosure')
        );
    }

    public function test_disclosure_header_uses_configured_name_and_message(): void
    {
        config()->set('ai-act-compliance.disclosure.header', 'X-Test-Disclosure');
        config()->set('ai-act-compliance.disclosure.message', 'Generated with AI assistance.');

        $middleware = new AiDisclosureMiddleware();

        $response = $middleware->handle(Request::create('/disclosure'), fn () => response('ok'));

        self::assertSame('Generated with AI assistance.', $response->headers->get('X-Test-Disclosure'));
    }

    public function test_disclosure_header_is_skipped_when_disabled(): void
    {
        config()->set('ai-act-compliance.disclosure.enabled', false);

        $middleware = new AiDisclosureMiddleware();

        $response = $middleware->handle(Request::create('/disclosure'), fn () => response('ok'));

        self::assertFalse($response->headers->has('X-AI-Disclosure'));
    }
}
