<?php

namespace Padosoft\AiActCompliance\Cybersecurity;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class PerUserRateLimitMiddleware
{
    public function handle(Request $request, Closure $next, int $max = 120)
    {
        $key = sprintf(
            'ai-act:%s:%s:%s',
            $request->route()?->uri() ?? $request->path(),
            $request->user()?->getAuthIdentifier() ?? 'guest',
            $request->ip()
        );

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new TooManyRequestsHttpException(max(RateLimiter::availableIn($key), 1), 'Rate limit exceeded.');
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
