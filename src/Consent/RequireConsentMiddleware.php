<?php

namespace Padosoft\AiActCompliance\Consent;

use Closure;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\Consent\Models\ConsentRecord;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RequireConsentMiddleware
{
    public function handle(Request $request, Closure $next, ?string $feature = null)
    {
        if ($feature === null || $feature === '') {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            throw new HttpException(401, 'Authentication required.');
        }

        $hasConsent = ConsentRecord::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('feature', $feature)
            ->where('granted', true)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasConsent) {
            throw new HttpException(403, 'Consent required for this feature.');
        }

        return $next($request);
    }
}
