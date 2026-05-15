<?php

namespace Padosoft\AiActCompliance\Alerting\Contracts;

interface AlertChannel
{
    /**
     * Stable identifier — 'slack', 'discord', 'email'. Persisted on
     * every `alert_dispatches.channel` row and used by the dispatcher
     * to look up per-tenant routes.
     */
    public function name(): string;

    /**
     * Deliver the payload to the wire. Implementations MUST classify
     * the outcome as ok / transient-failure / permanent-failure so
     * the dispatcher can decide retry vs trip.
     *
     * Channels MUST NOT throw — return a permanent-failure result
     * with an error message instead, so the audit trail captures the
     * problem.
     */
    public function send(AlertPayload $payload, string $endpoint): AlertDispatchResult;
}
