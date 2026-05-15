<?php

namespace Padosoft\AiActCompliance\Alerting\Services;

use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\Alerting\Models\AlertDispatch;

/**
 * Per-tenant + per-cohort + per-channel alert throttle.
 *
 * Without throttling a single drift detection that fires across
 * three cohort dimensions every snapshot run would spam every
 * configured channel until the metric recovers. The throttler
 * suppresses repeat dispatches inside a configurable window
 * (default 60 min) so the DPO sees one alert per (tenant, cohort,
 * channel, severity) bucket per cooldown period.
 */
class AlertThrottler
{
    public function __construct(
        private readonly int $perCohortMinutes,
    ) {}

    public function shouldSuppress(
        ?string $tenantId,
        string $channel,
        ?string $cohort,
        ?Carbon $now = null,
    ): bool {
        if ($this->perCohortMinutes <= 0) {
            return false;
        }

        $now ??= Carbon::now();
        $cutoff = $now->copy()->subMinutes($this->perCohortMinutes);

        // Query the denormalised `cohort` column (not a JSON path)
        // so the throttle stays portable across SQLite builds that
        // ship without JSON1 — Copilot review on PR #3 caught this.
        return AlertDispatch::query()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->where('cohort', $cohort)
            ->where('ok', true)
            ->where('created_at', '>=', $cutoff)
            ->exists();
    }
}
