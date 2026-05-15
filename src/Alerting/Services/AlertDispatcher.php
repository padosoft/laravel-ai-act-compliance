<?php

namespace Padosoft\AiActCompliance\Alerting\Services;

use Illuminate\Contracts\Container\Container;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Padosoft\AiActCompliance\Alerting\Models\AlertDispatch;
use Padosoft\AiActCompliance\Alerting\Models\AlertRoute;

/**
 * v1.3 alert cascade dispatcher.
 *
 * Resolution policy for a {@see AlertPayload}:
 *   1. Try Slack route (if enabled, severity matches, not tripped,
 *      not throttled).
 *   2. Else try Discord route.
 *   3. Always cc-fanout to email when an email route exists — email
 *      is the auditable backup trail, EXEMPT from the throttler so
 *      every drift event is recorded even if the user-facing
 *      webhook channels are quiet.
 *
 * Every attempt — success OR failure OR skip — writes an
 * `alert_dispatches` row so the operator has a complete audit trail.
 */
class AlertDispatcher
{
    public function __construct(
        private readonly Container $container,
        private readonly AlertThrottler $throttler,
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    /**
     * @return array<int, AlertDispatch>  rows persisted for this run
     */
    public function dispatch(AlertPayload $payload): array
    {
        $rows = [];

        $webhookCascadeSettled = false;
        foreach (['slack', 'discord'] as $channelName) {
            if ($webhookCascadeSettled) {
                break;
            }
            $route = $this->findRoute($payload->tenantId, $channelName);
            if ($route === null || ! $route->enabled) {
                continue;
            }
            // Cascade-level throttle pre-check: if the channel would
            // be throttled, treat the throttle skip as a
            // success-equivalent that ends the cascade. Otherwise a
            // previously-delivered Slack alert for (tenant, cohort)
            // would slide through to Discord, double-notifying the
            // operator on the secondary channel — Copilot iter-2
            // review on PR #3 caught this.
            if ($this->throttler->shouldSuppress(
                $route->tenant_id,
                $route->channel,
                $payload->cohort,
                $payload->severity,
            )) {
                $webhookCascadeSettled = true;
                continue;
            }
            $row = $this->attempt($payload, $route, throttle: false);
            if ($row !== null) {
                $rows[] = $row;
                if ($row->ok) {
                    // First success wins — fall through to email cc only.
                    $webhookCascadeSettled = true;
                    break;
                }
            }
        }

        // Email is the auditable backup trail. EXEMPT from throttle
        // so every drift event is recorded even when the user-facing
        // webhook channels are quiet — Copilot review on PR #3
        // caught the previous \"throttle defeats audit trail\" bug.
        $emailRoute = $this->findRoute($payload->tenantId, 'email');
        if ($emailRoute !== null && $emailRoute->enabled) {
            $row = $this->attempt($payload, $emailRoute, throttle: false);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function findRoute(?string $tenantId, string $channelName): ?AlertRoute
    {
        // Tenant-specific route first; fall back to the global
        // (tenant_id IS NULL) route when the tenant has not
        // configured its own. Copilot iter-2 review on PR #3
        // caught the gap — a tenant with a non-null tenant_id but
        // only a global route would receive no alert.
        if ($tenantId !== null) {
            $tenantRoute = AlertRoute::query()
                ->where('tenant_id', $tenantId)
                ->where('channel', $channelName)
                ->first();
            if ($tenantRoute !== null) {
                return $tenantRoute;
            }
        }

        return AlertRoute::query()
            ->whereNull('tenant_id')
            ->where('channel', $channelName)
            ->first();
    }

    private function attempt(AlertPayload $payload, AlertRoute $route, bool $throttle): ?AlertDispatch
    {
        // Severity filter — per-route opt-in list. When the route
        // carries a non-empty `severity_filter_json` and the payload
        // severity isn't in it, the channel is skipped silently (no
        // audit row — same posture as the throttler, to avoid noisy
        // \"filtered\" rows for routes that intentionally don't want
        // low-severity alerts).
        if (is_array($route->severity_filter_json)
            && $route->severity_filter_json !== []
            && ! in_array($payload->severity, $route->severity_filter_json, true)
        ) {
            return null;
        }

        if ($this->circuitBreaker->isTripped($route)) {
            // ASCII-only audit message — em-dash + literal long-dash
            // can surprise tooling that expects ASCII. Copilot iter-2
            // review PR #3.
            $until = $route->tripped_until !== null
                ? $route->tripped_until->toIso8601String()
                : '(unknown)';

            return $this->recordSkipped(
                $route,
                $payload,
                httpStatus: null,
                errorMessage: 'Channel tripped - cooldown until '.$until,
            );
        }

        if ($throttle && $this->throttler->shouldSuppress(
            $route->tenant_id,
            $route->channel,
            $payload->cohort,
            $payload->severity,
        )) {
            // Throttle suppression is NOT a failure — don't increment
            // consecutive_failures and don't write an audit row (the
            // earlier successful row inside the cooldown window IS
            // the audit row for the throttled period).
            return null;
        }

        // AlertRoute::webhook_url accessor decrypts; if the row was
        // ever inserted via raw SQL (seeders, manual migration) it
        // would throw DecryptException. Catch + record a permanent-
        // failure audit row instead of crashing every flow that
        // touches the route — Copilot iter-2 review PR #3.
        try {
            $endpoint = $route->channel === 'email'
                ? ($route->email ?? '')
                : ($route->webhook_url ?? '');
        } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {
            return $this->recordSkipped(
                $route,
                $payload,
                httpStatus: null,
                errorMessage: 'AlertRoute webhook_url decryption failed: '.$exception->getMessage(),
            );
        }

        if ($endpoint === '') {
            return $this->recordSkipped(
                $route,
                $payload,
                httpStatus: null,
                errorMessage: 'Endpoint missing on alert_routes row',
            );
        }

        $channel = $this->resolveChannel($route->channel);
        if ($channel === null) {
            // Misconfigured channel binding — record a permanent
            // failure audit row instead of crashing the originating
            // capture() call. Copilot review on PR #3 caught the
            // previous throw-on-misconfig hazard.
            return $this->recordSkipped(
                $route,
                $payload,
                httpStatus: null,
                errorMessage: 'Channel binding missing or invalid in config(ai-act-compliance.alerting.channels.'.$route->channel.')',
            );
        }

        $result = $channel->send($payload, $endpoint);
        $this->circuitBreaker->record($route, $result->ok);

        return AlertDispatch::query()->create([
            'tenant_id' => $route->tenant_id,
            'alert_route_id' => $route->id,
            'channel' => $route->channel,
            'severity' => $payload->severity,
            'title' => $payload->title,
            'cohort' => $payload->cohort,
            'payload_json' => $payload->toArray(),
            'ok' => $result->ok,
            'transient_failure' => $result->transient,
            'http_status' => $result->httpStatus,
            'error_message' => $result->errorMessage,
        ]);
    }

    private function recordSkipped(
        AlertRoute $route,
        AlertPayload $payload,
        ?int $httpStatus,
        string $errorMessage,
    ): AlertDispatch {
        return AlertDispatch::query()->create([
            'tenant_id' => $route->tenant_id,
            'alert_route_id' => $route->id,
            'channel' => $route->channel,
            'severity' => $payload->severity,
            'title' => $payload->title,
            'cohort' => $payload->cohort,
            'payload_json' => $payload->toArray(),
            'ok' => false,
            'transient_failure' => false,
            'http_status' => $httpStatus,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Resolve a channel instance from the config map. Returns null
     * on missing / non-implementing binding so the dispatcher writes
     * a permanent-failure audit row rather than crashing the whole
     * BiasMonitorService::capture() call.
     */
    private function resolveChannel(string $name): ?AlertChannel
    {
        $fqcn = config('ai-act-compliance.alerting.channels.'.$name);
        if (! is_string($fqcn) || ! class_exists($fqcn) || ! is_subclass_of($fqcn, AlertChannel::class)) {
            return null;
        }

        $instance = $this->container->make($fqcn);

        return $instance instanceof AlertChannel ? $instance : null;
    }
}
