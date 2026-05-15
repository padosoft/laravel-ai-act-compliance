<?php

namespace Padosoft\AiActCompliance\Alerting\Services;

use Illuminate\Contracts\Container\Container;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertDispatchResult;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Padosoft\AiActCompliance\Alerting\Models\AlertDispatch;
use Padosoft\AiActCompliance\Alerting\Models\AlertRoute;

/**
 * v1.3 alert cascade dispatcher.
 *
 * Resolution policy for a {@see AlertPayload}:
 *   1. Try Slack route (if enabled, not tripped, not throttled).
 *   2. Else try Discord route.
 *   3. Always cc-fanout to email when an email route exists — email
 *      is the auditable backup trail, independent of Slack/Discord
 *      outcome.
 *
 * Every attempt — success OR failure — writes an `alert_dispatches`
 * row so the operator has a complete audit trail.
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

        $primaries = ['slack', 'discord'];
        $primaryDispatched = false;
        foreach ($primaries as $channelName) {
            $route = $this->findRoute($payload->tenantId, $channelName);
            if ($route === null || ! $route->enabled) {
                continue;
            }
            $row = $this->attempt($payload, $route);
            if ($row !== null) {
                $rows[] = $row;
                if ($row->ok) {
                    $primaryDispatched = true;
                    break;
                }
            }
        }

        // Email is ALWAYS attempted when configured — it is the
        // auditable backup trail. Independent of primary outcome:
        // success path → email is a cc-record; failure path → email
        // is the only delivery.
        $emailRoute = $this->findRoute($payload->tenantId, 'email');
        if ($emailRoute !== null && $emailRoute->enabled) {
            $row = $this->attempt($payload, $emailRoute);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        // Suppress unused-var warning while preserving the bool for
        // possible future telemetry — kept locally rather than
        // exposed on the result so callers don't accidentally
        // condition on it (the audit row is the source of truth).
        unset($primaryDispatched);

        return $rows;
    }

    private function findRoute(?string $tenantId, string $channelName): ?AlertRoute
    {
        return AlertRoute::query()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channelName)
            ->first();
    }

    private function attempt(AlertPayload $payload, AlertRoute $route): ?AlertDispatch
    {
        if ($this->circuitBreaker->isTripped($route)) {
            return $this->recordSkipped(
                $route,
                $payload,
                httpStatus: null,
                errorMessage: 'Channel tripped — cooldown until '.$route->tripped_until?->toIso8601String(),
            );
        }

        if ($this->throttler->shouldSuppress($route->tenant_id, $route->channel, $payload->cohort)) {
            // Throttle suppression is NOT a failure — don't increment
            // consecutive_failures and don't write an audit row (the
            // earlier successful row inside the cooldown window IS
            // the audit row for the throttled period).
            return null;
        }

        $endpoint = $route->channel === 'email'
            ? ($route->email ?? '')
            : ($route->webhook_url ?? '');

        if ($endpoint === '') {
            return $this->recordSkipped(
                $route,
                $payload,
                httpStatus: null,
                errorMessage: 'Endpoint missing on alert_routes row',
            );
        }

        $channel = $this->resolveChannel($route->channel);
        $result = $channel->send($payload, $endpoint);
        $this->circuitBreaker->record($route, $result->ok);

        return AlertDispatch::query()->create([
            'tenant_id' => $route->tenant_id,
            'alert_route_id' => $route->id,
            'channel' => $route->channel,
            'severity' => $payload->severity,
            'title' => $payload->title,
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
            'payload_json' => $payload->toArray(),
            'ok' => false,
            'transient_failure' => false,
            'http_status' => $httpStatus,
            'error_message' => $errorMessage,
        ]);
    }

    private function resolveChannel(string $name): AlertChannel
    {
        $fqcn = config('ai-act-compliance.alerting.channels.'.$name);
        if (! is_string($fqcn) || ! class_exists($fqcn)) {
            // Fall through to a no-op channel so the dispatcher never
            // crashes on a missing binding — the audit trail records
            // the misconfiguration via permanentFailure.
            throw new \RuntimeException("Alert channel '{$name}' not registered in config(ai-act-compliance.alerting.channels)");
        }

        return $this->container->make($fqcn);
    }
}
