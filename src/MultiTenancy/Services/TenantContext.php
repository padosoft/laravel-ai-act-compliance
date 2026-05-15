<?php

namespace Padosoft\AiActCompliance\MultiTenancy\Services;

use Padosoft\AiActCompliance\MultiTenancy\Models\Tenant;

/**
 * Holds the active tenant for the current request / job / CLI run.
 *
 * Singleton-scoped per request via the service provider so all
 * package services see the same `$current` value without having to
 * thread it through every method signature. Hosts that don't use
 * multi-tenancy can leave it null — the package degrades to its
 * v1.4 behaviour (every query uses the bare scope).
 */
class TenantContext
{
    private ?Tenant $current = null;

    public function set(?Tenant $tenant): void
    {
        $this->current = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function currentSlug(): ?string
    {
        return $this->current?->slug;
    }

    public function isSet(): bool
    {
        return $this->current !== null;
    }

    /**
     * Resolve a slug → Tenant lazily. Caches the model so repeated
     * calls inside the same request don't re-query the DB.
     */
    public function activate(string $slug): ?Tenant
    {
        if ($this->current?->slug === $slug) {
            return $this->current;
        }
        $tenant = Tenant::query()->bySlug($slug)->first();
        $this->current = $tenant;

        return $tenant;
    }
}
