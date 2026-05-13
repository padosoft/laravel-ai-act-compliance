<?php

namespace Padosoft\AiActCompliance\Cybersecurity;

use Closure;
use Illuminate\Http\Request;

class SessionAnomalyDetectionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
