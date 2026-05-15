<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Contracts;

/**
 * Per-cohort metric outcome inside a {@see MetricResult}.
 *
 * Immutable value object. Fields:
 *  - cohort     The cohort label (e.g. 'language=it', 'gender=f').
 *  - sampleSize Number of observations the metric ingested for this cohort.
 *  - value      The metric score for this cohort.
 *  - ciLow      Lower bound of the 95% confidence interval.
 *  - ciHigh     Upper bound of the 95% confidence interval.
 *  - flagged    True when the cohort exceeds the configured disparity threshold.
 */
final class CohortMetric
{
    public function __construct(
        public readonly string $cohort,
        public readonly int $sampleSize,
        public readonly float $value,
        public readonly float $ciLow,
        public readonly float $ciHigh,
        public readonly bool $flagged,
    ) {}

    public function toArray(): array
    {
        return [
            'cohort' => $this->cohort,
            'sample_size' => $this->sampleSize,
            'value' => $this->value,
            'ci_low' => $this->ciLow,
            'ci_high' => $this->ciHigh,
            'flagged' => $this->flagged,
        ];
    }
}
