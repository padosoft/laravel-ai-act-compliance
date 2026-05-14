<?php

namespace Padosoft\AiActCompliance\Disclosure;

use Closure;
use Illuminate\Http\Request;

class AiDisclosureMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! config('ai-act-compliance.disclosure.enabled', true)) {
            return $response;
        }

        $header = (string) config('ai-act-compliance.disclosure.header', 'X-AI-Disclosure');
        $message = (string) config('ai-act-compliance.disclosure.message', 'This response may include AI-generated content.');

        $response->headers->set($header, $message);

        return $response;
    }
}
