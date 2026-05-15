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
    public function __construct(
        private readonly int $failuresToTrip,
        private readonly int $cooldownMinutes,
    ) {}

    public function isTripped(AlertRoute $route, ?Carbon $now = null): bool
    {
        $tripped = $route->tripped_until;
        if ($tripped === null) {
            return false;
        }
        $now ??= Carbon::now();

        return $tripped->isFuture() && $now->lessThan($tripped);
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
