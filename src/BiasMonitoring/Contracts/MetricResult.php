<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Contracts;

use Carbon\CarbonImmutable;

/**
 * Structured result returned by every {@see CohortParityMetric::compute()}.
 *
 * Replaces the bare `array` return of v1.x metrics. Keeps the original
 * computed payload available via {@see toArray()} so callers that
 * persist the snapshot can serialise the full breakdown into the
 * `payload` JSON column.
 *
 * Fields:
 *  - metricName       The registry key (e.g. 'demographic_parity').
 *  - cohortDimension  The dimension dimension this run was computed against
 *                     (e.g. 'language'). One MetricResult covers one
 *                     dimension; callers loop dimensions to capture all.
 *  - cohortBreakdowns Per-cohort outcomes.
 *  - disparityScore   Aggregate parity gap. Higher = worse. Range [0, 1].
 *  - worstCohort      Cohort label with the highest disparity, or null
 *                     when all cohorts are below threshold.
 *  - articleEvidence  AI Act articles this metric provides evidence for.
 *  - computedAt       Time the metric was computed.
 */
final class MetricResult
{
    /**
     * @param  array<int, CohortMetric>  $cohortBreakdowns
     * @param  array<int, string>  $articleEvidence
     */
    public function __construct(
        public readonly string $metricName,
        public readonly string $cohortDimension,
        public readonly array $cohortBreakdowns,
        public readonly float $disparityScore,
        public readonly ?string $worstCohort,
        public readonly array $articleEvidence,
        public readonly CarbonImmutable $computedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'metric_name' => $this->metricName,
            'cohort_dimension' => $this->cohortDimension,
            'cohort_breakdowns' => array_map(
                static fn (CohortMetric $cohort): array => $cohort->toArray(),
                $this->cohortBreakdowns,
            ),
            'disparity_score' => $this->disparityScore,
            'worst_cohort' => $this->worstCohort,
            'article_evidence' => $this->articleEvidence,
            'computed_at' => $this->computedAt->toIso8601String(),
        ];
    }
}
