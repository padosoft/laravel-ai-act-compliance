<?php

namespace Padosoft\AiActCompliance\MultiTenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\MultiTenancy\Enums\TenantStatus;
use Padosoft\AiActCompliance\MultiTenancy\Services\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the active tenant from the request and bind it on the
 * shared {@see TenantContext} singleton.
 *
 * Resolution order:
 *
 *   1. `X-Tenant-Id` header
 *   2. `tenant` query string
 *
 * Tenants in `suspended` status get HTTP 423 (Locked); `archived`
 * tenants get HTTP 410 (Gone). An unknown slug → HTTP 404 with a
 * JSON error body. When NO tenant header is present the request
 * passes through with `$context->current() === null` — multi-tenant
 * is opt-in, so hosts running single-tenant see no behavioural
 * change.
 */
class TenantContextMiddleware
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Defensively reset the context at the START of every request.
        // The SP binds TenantContext as `scoped`, but a host that
        // accidentally re-binds it as a singleton (Octane warm
        // container, queue worker reusing the container) could leak
        // the previous request's tenant into a no-header request and
        // attribute writes to the wrong tenant. Copilot iter-1 review
        // on PR #5.
        $this->context->set(null);

        $slug = $request->header('X-Tenant-Id');
        if ($slug === null || trim((string) $slug) === '') {
            $slug = $request->query('tenant');
        }
        $slug = is_string($slug) ? trim($slug) : null;
        if ($slug === null || $slug === '') {
            return $next($request);
        }

        $tenant = $this->context->activate($slug);
        if ($tenant === null) {
            return response()->json(
                ['error' => 'tenant not found', 'slug' => $slug],
                status: 404,
            );
        }
        if ($tenant->status === TenantStatus::Suspended->value) {
            return response()->json(
                ['error' => 'tenant suspended', 'slug' => $slug],
                status: 423,
            );
        }
        if ($tenant->status === TenantStatus::Archived->value) {
            return response()->json(
                ['error' => 'tenant archived', 'slug' => $slug],
                status: 410,
            );
        }

        return $next($request);
    }
}
