<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Contracts;

/**
 * Metric variant that carries its registry name + regulatory citations.
 *
 * v1.2+ reference metrics (Demographic Parity, Equalized Odds,
 * Calibration) implement this interface so {@see \Padosoft\AiActCompliance\BiasMonitoring\Services\MetricRegistry}
 * can resolve them by stable name and the audit trail can record the
 * AI Act articles each row provides evidence for.
 *
 * Extends {@see CohortParityMetric} additively — pre-v1.2 metrics that
 * implement only the parent interface continue to work via the single-
 * metric call path; only the registry-dispatched path requires this
 * extension.
 */
interface NamedCohortMetric extends CohortParityMetric
{
    /**
     * Stable identifier used to register and resolve the metric.
     * Snake-case is the convention (e.g. 'demographic_parity').
     */
    public function name(): string;

    /**
     * AI Act articles this metric provides evidence for, persisted on
     * each snapshot row under `article_evidence_json`.
     *
     * @return array<int, string>
     */
    public function articleReferences(): array;

    /**
     * Version stamp for the metric's algorithm. Persisted on every
     * snapshot under `metric_version` so the audit trail can
     * distinguish results produced by different revisions of the
     * SAME named metric (e.g. when a reference formula evolves
     * between v1.x and v2.x). Reference metrics return `'1.0'`;
     * host-app custom metrics SHOULD bump this on every algorithmic
     * change.
     */
    public function version(): string;
}
