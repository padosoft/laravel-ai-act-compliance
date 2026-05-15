<?php

namespace Padosoft\AiActCompliance\Alerting\Services;

use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\Alerting\Models\AlertRoute;

/**
 * Per-channel circuit breaker.
 *
 * After {@see CircuitBreaker::$failuresToTrip} consecutive failures
 * on a route, the channel is **tripped**: subsequent dispatches skip
 * it until {@see CircuitBreaker::$cooldownMinutes} have elapsed since
 * the last failure. The dispatcher consults
 * {@see isTripped()} BEFORE attempting a send and {@see record()}
 * AFTER each attempt to update consecutive-failure counts.
 *
 * The state lives entirely on `alert_routes` (no separate cache /
 * KV) so it survives worker restarts and is auditable.
 */
class CircuitBreaker
{
    private readonly int $failuresToTrip;

    private readonly int $cooldownMinutes;

    public function __construct(int $failuresToTrip, int $cooldownMinutes)
    {
        // Clamp misconfigured env vars: AI_ACT_ALERT_CB_FAILURES=0 would
        // trip the breaker on the first failure (1 >= 0) and silently
        // disable every channel; a negative cooldown would either
        // permanently arm the trip or never expire it. Both
        // misconfigurations defeat the alerting cascade — treat them
        // as fatal-misconfig and fall back to sane minimums. Copilot
        // iter-3 review on PR #3.
        $this->failuresToTrip = max(1, $failuresToTrip);
        $this->cooldownMinutes = max(0, $cooldownMinutes);
    }

    public function isTripped(AlertRoute $route, ?Carbon $now = null): bool
    {
        $tripped = $route->tripped_until;
        if ($tripped === null) {
            return false;
        }
        $now ??= Carbon::now();

        if ($tripped->greaterThan($now)) {
            return true;
        }

        // Natural cooldown elapsed without intervening traffic — reset
        // counters here so the route gets a fresh failure budget
        // instead of re-tripping on the very next failure (which would
        // happen if consecutive_failures stays at the trip threshold).
        // Copilot iter-3 review on PR #3.
        if ($route->consecutive_failures > 0 || $route->tripped_until !== null) {
            $route->forceFill([
                'consecutive_failures' => 0,
                'tripped_until' => null,
            ])->save();
        }

        return false;
    }

    public function record(AlertRoute $route, bool $success, ?Carbon $now = null): void
    {
        $now ??= Carbon::now();

        if ($success) {
            $route->forceFill([
                'last_success_at' => $now,
                'consecutive_failures' => 0,
                'tripped_until' => null,
            ])->save();

            return;
        }

        $consecutive = $route->consecutive_failures + 1;
        $update = [
            'last_failure_at' => $now,
            'consecutive_failures' => $consecutive,
        ];
        if ($consecutive >= $this->failuresToTrip) {
            $update['tripped_until'] = $now->copy()->addMinutes($this->cooldownMinutes);
        }
        $route->forceFill($update)->save();
    }
}
