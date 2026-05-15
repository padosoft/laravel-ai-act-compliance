<?php

namespace Padosoft\AiActCompliance\MultiTenancy\Services;

use Illuminate\Support\Facades\DB;
use Padosoft\AiActCompliance\MultiTenancy\Models\Tenant;

/**
 * Aggregates per-tenant DSAR / risk / incident / bias / regulatory
 * counts for the DPO console cross-tenant dashboard.
 *
 * All queries are scoped by `tenant_id` (tenant-aware tables) or
 * left unscoped (global tables — no per-tenant column). Tables that
 * the package introduced before v1.5 don't all carry `tenant_id`;
 * those simply omit per-tenant counts and surface a single
 * platform-wide total under the `global_*` keys.
 */
class CrossTenantOverviewService
{
    /**
     * @return array{
     *     tenants: array<int, array<string,mixed>>,
     *     totals: array<string,int>
     * }
     */
    public function compile(): array
    {
        $tenants = Tenant::query()->orderBy('slug')->get();
        $rows = [];
        foreach ($tenants as $tenant) {
            $rows[] = [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'subscription_tier' => $tenant->subscription_tier,
                'status' => $tenant->status,
                'dpo_email' => $tenant->dpo_email,
                'kpis' => $this->kpisForTenant($tenant->slug),
            ];
        }

        return [
            'tenants' => $rows,
            'totals' => $this->platformTotals(),
        ];
    }

    /**
     * @return array<string,int>
     */
    public function kpisForTenant(string $slug): array
    {
        return [
            'alert_routes' => $this->safeCount('alert_routes', $slug),
            'alert_dispatches' => $this->safeCount('alert_dispatches', $slug),
            'regulatory_amendments' => $this->safeCount('regulatory_amendments', $slug),
            'pending_amendments' => $this->safeCount(
                'regulatory_amendments',
                $slug,
                ['status' => 'pending'],
            ),
        ];
    }

    /**
     * @return array<string,int>
     */
    private function platformTotals(): array
    {
        return [
            'tenants_total' => (int) Tenant::query()->count(),
            'tenants_active' => (int) Tenant::query()->where('status', 'active')->count(),
            'tenants_suspended' => (int) Tenant::query()->where('status', 'suspended')->count(),
            'alert_dispatches_total' => $this->safeCount('alert_dispatches'),
            'regulatory_amendments_total' => $this->safeCount('regulatory_amendments'),
            'fria_assessments_total' => $this->safeCount('fria_assessments'),
            'incidents_total' => $this->safeCount('incidents'),
        ];
    }

    /**
     * Defensive count: tables can be missing under partial test
     * bootstraps. Returns 0 rather than throwing so the dashboard
     * always renders.
     *
     * @param  array<string,scalar>  $extraFilters
     */
    private function safeCount(string $table, ?string $tenantSlug = null, array $extraFilters = []): int
    {
        try {
            $query = DB::table($table);
            if ($tenantSlug !== null) {
                $query->where('tenant_id', $tenantSlug);
            }
            foreach ($extraFilters as $col => $val) {
                $query->where($col, $val);
            }

            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
