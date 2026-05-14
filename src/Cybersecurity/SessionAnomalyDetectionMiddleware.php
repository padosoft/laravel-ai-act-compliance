<?php

namespace Padosoft\AiActCompliance\Cybersecurity;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SessionAnomalyDetectionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->hasSession()) {
            $session = $request->session();
            $sessionKeyPrefix = 'ai-act-compliance.session-fingerprint';
            $currentIp = $request->ip();
            $currentUserAgent = (string) $request->userAgent();
            $storedIp = $session->get($sessionKeyPrefix . '.ip');
            $storedUserAgent = $session->get($sessionKeyPrefix . '.user_agent');

            if (
                ($storedIp !== null && $storedIp !== $currentIp)
                || ($storedUserAgent !== null && $storedUserAgent !== $currentUserAgent)
            ) {
                throw new HttpException(403, 'Session anomaly detected.');
            }

            $session->put($sessionKeyPrefix . '.ip', $currentIp);
            $session->put($sessionKeyPrefix . '.user_agent', $currentUserAgent);
        }

        return $next($request);
    }
}
