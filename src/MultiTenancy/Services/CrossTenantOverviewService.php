<?php

namespace Padosoft\AiActCompliance\MultiTenancy\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        // Pre-aggregate the per-table per-tenant counts in ONE query
        // per table (GROUP BY tenant_id). Avoids the N+1 walk the
        // previous version of `compile()` did — Copilot iter-1 PR #5.
        // The result is keyed by tenant_slug for cheap lookup.
        $alertCounts = $this->groupCount('alert_dispatches');
        $alertRouteCounts = $this->groupCount('alert_routes');
        $amendmentCounts = $this->groupCount('regulatory_amendments');
        $pendingAmendmentCounts = $this->groupCount(
            'regulatory_amendments',
            ['status' => 'pending'],
        );

        $rows = [];
        foreach ($tenants as $tenant) {
            $rows[] = [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'subscription_tier' => $tenant->subscription_tier,
                'status' => $tenant->status,
                'dpo_email' => $tenant->dpo_email,
                'kpis' => [
                    'alert_routes' => $alertRouteCounts[$tenant->slug] ?? 0,
                    'alert_dispatches' => $alertCounts[$tenant->slug] ?? 0,
                    'regulatory_amendments' => $amendmentCounts[$tenant->slug] ?? 0,
                    'pending_amendments' => $pendingAmendmentCounts[$tenant->slug] ?? 0,
                ],
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
            // The package's incidents table is `incident_tickets`
            // (NOT `incidents`). Copilot iter-1 review on PR #5
            // caught the typo that made `incidents_total` always 0.
            'incidents_total' => $this->safeCount('incident_tickets'),
        ];
    }

    /**
     * One-query GROUP BY tenant_id → counts. The keyset is the slug
     * (tenant_id column). Falls through to an empty map on a missing
     * table (test bootstraps that load only a subset of migrations);
     * any OTHER query exception is logged and re-thrown to surface
     * connectivity / permission failures instead of silently lying.
     *
     * @param  array<string,scalar>  $extraFilters
     * @return array<string,int>
     */
    private function groupCount(string $table, array $extraFilters = []): array
    {
        try {
            $query = DB::table($table)
                ->whereNotNull('tenant_id')
                ->select('tenant_id', DB::raw('COUNT(*) as c'))
                ->groupBy('tenant_id');
            foreach ($extraFilters as $col => $val) {
                $query->where($col, $val);
            }
            $rows = $query->get();

            return $rows
                ->mapWithKeys(fn ($row) => [(string) $row->tenant_id => (int) $row->c])
                ->all();
        } catch (QueryException $exception) {
            if (! $this->looksLikeMissingTable($exception)) {
                Log::warning('ai-act regulatory groupCount failed', [
                    'table' => $table,
                    'message' => $exception->getMessage(),
                ]);
                throw $exception;
            }

            return [];
        }
    }

    /**
     * Defensive count: tables can be missing under partial test
     * bootstraps. Returns 0 ONLY on the specific "table missing"
     * QueryException; any other DB error is logged + re-thrown so
     * real operational failures don't lie to the dashboard.
     * Copilot iter-1 review on PR #5.
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
        } catch (QueryException $exception) {
            if (! $this->looksLikeMissingTable($exception)) {
                Log::warning('ai-act regulatory safeCount failed', [
                    'table' => $table,
                    'message' => $exception->getMessage(),
                ]);
                throw $exception;
            }

            return 0;
        }
    }

    private function looksLikeMissingTable(QueryException $exception): bool
    {
        $msg = $exception->getMessage();
        // Cross-driver "table missing" signatures.
        return str_contains($msg, 'no such table') // SQLite
            || str_contains($msg, "doesn't exist")  // MySQL ER_NO_SUCH_TABLE
            || str_contains($msg, 'does not exist') // Postgres undefined_table
            || str_contains($msg, 'Invalid object name'); // MSSQL
    }
}
