<?php

namespace Padosoft\AiActCompliance\Alerting\Contracts;

/**
 * Single-method contract for an outbound alert channel.
 *
 * Channel identity is sourced from the config key + `alert_routes.channel`
 * column — the dispatcher resolves a channel instance by config FQCN
 * lookup, not by interface-level naming. Channel implementations
 * MUST NOT throw; permanent / transient failures are returned via
 * {@see AlertDispatchResult} so the audit trail records the outcome.
 */
interface AlertChannel
{
    /**
     * Deliver the payload to the wire. Implementations MUST classify
     * the outcome as ok / transient-failure / permanent-failure so
     * the dispatcher can decide retry vs trip. Channels MUST NOT
     * throw — return a permanent-failure result with an error
     * message instead, so the audit trail captures the problem.
     */
    public function send(AlertPayload $payload, string $endpoint): AlertDispatchResult;
}
