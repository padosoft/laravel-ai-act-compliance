<?php

namespace Padosoft\AiActCompliance\Alerting\Contracts;

/**
 * Immutable payload describing an alert that one or more
 * {@see AlertChannel}s should deliver.
 *
 * Channel-agnostic — the Slack / Discord / Email channels each
 * format this VO into their own wire shape.
 */
final class AlertPayload
{
    /**
     * @param  array<int, string>  $articles  AI Act articles cited as
     *                                        evidence (e.g. ['Art. 10']).
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $tenantId,
        public readonly ?string $evidenceUrl,
        public readonly ?string $metricName,
        public readonly ?string $cohort,
        public readonly array $articles = [],
    ) {}

    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'title' => $this->title,
            'body' => $this->body,
            'tenant_id' => $this->tenantId,
            'evidence_url' => $this->evidenceUrl,
            'metric_name' => $this->metricName,
            'cohort' => $this->cohort,
            'articles' => $this->articles,
        ];
    }
}
