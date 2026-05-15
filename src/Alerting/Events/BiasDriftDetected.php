<?php

namespace Padosoft\AiActCompliance\Alerting\Events;

/**
 * Raised by {@see \Padosoft\AiActCompliance\BiasMonitoring\Services\BiasMonitorService}
 * (or any v1.2+ caller that constructs the event directly) when a
 * snapshot's disparity score exceeds the configured threshold.
 *
 * The v1.3 {@see \Padosoft\AiActCompliance\Alerting\Listeners\BiasDriftDetectedListener}
 * subscribes to this event and triggers the per-tenant alert cascade.
 * Hosts that don't want alerting simply leave
 * `ai-act-compliance.alerting.enabled=false` (the default).
 */
final class BiasDriftDetected
{
    /**
     * @param  array<int, string>  $articleEvidence
     */
    public function __construct(
        public readonly ?string $tenantId,
        public readonly string $metricName,
        public readonly ?string $cohort,
        public readonly float $disparityScore,
        public readonly ?string $evidenceUrl = null,
        public readonly array $articleEvidence = [],
    ) {}
}
