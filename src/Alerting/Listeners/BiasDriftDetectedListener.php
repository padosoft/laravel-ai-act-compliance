<?php

namespace Padosoft\AiActCompliance\Alerting\Listeners;

use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Padosoft\AiActCompliance\Alerting\Events\BiasDriftDetected;
use Padosoft\AiActCompliance\Alerting\Services\AlertDispatcher;

/**
 * Translate a {@see BiasDriftDetected} domain event into an
 * {@see AlertPayload} and hand it to the dispatcher's cascade.
 */
class BiasDriftDetectedListener
{
    public function __construct(private readonly AlertDispatcher $dispatcher) {}

    public function handle(BiasDriftDetected $event): void
    {
        if (! config('ai-act-compliance.alerting.enabled', false)) {
            return;
        }

        $severity = $this->severityFor($event->disparityScore);

        $body = sprintf(
            'Disparity score %s detected on metric %s for cohort %s.',
            number_format($event->disparityScore, 4),
            $event->metricName,
            $event->cohort ?? '(unknown)',
        );

        $payload = new AlertPayload(
            severity: $severity,
            title: 'Bias drift detected on '.$event->metricName,
            body: $body,
            tenantId: $event->tenantId,
            evidenceUrl: $event->evidenceUrl,
            metricName: $event->metricName,
            cohort: $event->cohort,
            articles: $event->articleEvidence,
        );

        $this->dispatcher->dispatch($payload);
    }

    private function severityFor(float $disparity): string
    {
        // Tunable bands. The dispatcher persists the severity onto
        // every audit row so an operator can filter the UI by
        // severity later.
        if ($disparity >= 0.20) {
            return 'critical';
        }
        if ($disparity >= 0.10) {
            return 'high';
        }
        if ($disparity >= 0.05) {
            return 'medium';
        }

        return 'low';
    }
}
