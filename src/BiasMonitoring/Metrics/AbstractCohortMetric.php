<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Metrics;

use Carbon\CarbonImmutable;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\MetricResult;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\NamedCohortMetric;

/**
 * Shared helpers for the v1.2 reference metrics.
 *
 * Provides cohort bucketing + Wilson 95% CI bootstrap so each subclass
 * focuses purely on its parity formula. Subclasses implement
 * {@see scoreCohort()} (the per-cohort statistic) and {@see disparity()}
 * (the aggregate disparity across cohorts).
 *
 * The base class implements both the legacy {@see compute()} (returns
 * `array` for v1.1 API parity) and a structured
 * {@see computeResult()} returning {@see MetricResult}. v1.2 callers
 * (BiasMonitorService through MetricRegistry) prefer the structured
 * variant; the array shape is preserved for snapshot persistence.
 */
abstract class AbstractCohortMetric implements NamedCohortMetric
{
    /**
     * Default version stamp for v1.2 reference metrics. Subclasses
     * override this when the per-metric algorithm evolves so the
     * audit trail can distinguish results across revisions.
     */
    public function version(): string
    {
        return '1.0';
    }

    public function compute(array $context = []): array
    {
        return $this->computeResult($context)->toArray();
    }

    public function computeResult(array $context = []): MetricResult
    {
        $dimension = (string) ($context['cohort_dimension'] ?? 'global');
        $cohorts = $this->bucketByCohort($context);
        $threshold = (float) ($context['disparity_threshold'] ?? 0.05);

        $breakdowns = [];
        foreach ($cohorts as $cohort => $observations) {
            $sampleSize = count($observations);
            $value = $this->scoreCohort($observations);
            [$low, $high] = $this->wilsonInterval($value, $sampleSize);
            $breakdowns[] = new CohortMetric(
                cohort: (string) $cohort,
                sampleSize: $sampleSize,
                value: $value,
                ciLow: $low,
                ciHigh: $high,
                flagged: false,
            );
        }

        $disparityScore = $this->disparity($breakdowns);
        $breakdowns = $this->markFlagged($breakdowns, $disparityScore, $threshold);
        $worst = $this->worstCohort($breakdowns, $disparityScore, $threshold);

        return new MetricResult(
            metricName: $this->name(),
            cohortDimension: $dimension,
            cohortBreakdowns: $breakdowns,
            disparityScore: $disparityScore,
            worstCohort: $worst,
            articleEvidence: $this->articleReferences(),
            computedAt: CarbonImmutable::now(),
        );
    }

    /**
     * Per-cohort statistic. Override in concrete metrics.
     *
     * @param  array<int, array<string, mixed>>  $observations
     */
    abstract protected function scoreCohort(array $observations): float;

    /**
     * Aggregate disparity across cohorts. Default: max - min spread.
     *
     * @param  array<int, CohortMetric>  $breakdowns
     */
    protected function disparity(array $breakdowns): float
    {
        if ($breakdowns === []) {
            return 0.0;
        }
        $values = array_map(static fn (CohortMetric $c): float => $c->value, $breakdowns);

        return round(max($values) - min($values), 6);
    }

    /**
     * Bucket the raw observations by cohort label.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function bucketByCohort(array $context): array
    {
        $observations = $context['observations'] ?? [];
        if (! is_array($observations)) {
            return [];
        }

        $buckets = [];
        foreach ($observations as $observation) {
            if (! is_array($observation)) {
                continue;
            }
            $cohort = (string) ($observation['cohort'] ?? 'global');
            $buckets[$cohort] ??= [];
            $buckets[$cohort][] = $observation;
        }

        return $buckets;
    }

    /**
     * Wilson score interval (95% CI) for a binomial proportion.
     *
     * Statistically correct for {@see DemographicParityMetric} where
     * the per-cohort statistic IS a binomial proportion
     * (P(prediction=positive | cohort)). Used as a COARSE
     * APPROXIMATION for the other reference metrics:
     *  - {@see EqualizedOddsMetric} overrides `computeResult()` and
     *    applies Wilson to the worst of TPR / FPR individually, which
     *    is appropriate because TPR and FPR are each binomial
     *    proportions in their respective conditional populations.
     *  - {@see CalibrationMetric} computes `|mean(score) − mean(label)|`
     *    which is NOT a binomial proportion. The Wilson interval there
     *    is reported as a coarse fallback; consumers needing strict
     *    intervals should compute a bootstrap CI or supply a
     *    metric-specific implementation by overriding this method.
     *
     * Subclasses may override this method to supply a more appropriate
     * interval (bootstrap, delta method, etc.) for their statistic.
     *
     * @return array{0: float, 1: float} [low, high]
     */
    protected function wilsonInterval(float $value, int $sampleSize): array
    {
        if ($sampleSize <= 0) {
            return [0.0, 0.0];
        }

        $z = 1.96; // 95% CI
        $denom = 1 + ($z * $z) / $sampleSize;
        $centre = ($value + ($z * $z) / (2 * $sampleSize)) / $denom;
        $half = ($z * sqrt(($value * (1 - $value) + ($z * $z) / (4 * $sampleSize)) / $sampleSize)) / $denom;

        $low = max(0.0, round($centre - $half, 6));
        $high = min(1.0, round($centre + $half, 6));

        return [$low, $high];
    }

    /**
     * @param  array<int, CohortMetric>  $breakdowns
     * @return array<int, CohortMetric>
     */
    private function markFlagged(array $breakdowns, float $disparityScore, float $threshold): array
    {
        if ($breakdowns === []) {
            return $breakdowns;
        }

        $values = array_map(static fn (CohortMetric $c): float => $c->value, $breakdowns);
        $median = $this->median($values);

        $out = [];
        foreach ($breakdowns as $cohort) {
            $flagged = $disparityScore > $threshold && abs($cohort->value - $median) > ($threshold / 2);
            $out[] = new CohortMetric(
                cohort: $cohort->cohort,
                sampleSize: $cohort->sampleSize,
                value: $cohort->value,
                ciLow: $cohort->ciLow,
                ciHigh: $cohort->ciHigh,
                flagged: $flagged,
            );
        }

        return $out;
    }

    /**
     * Resolve the cohort label most responsible for the disparity, or
     * `null` when the run is parity-clean (disparity at-or-below the
     * configured threshold). The contract on MetricResult promises a
     * null sentinel in that case so the admin SPA can render an
     * empty-state instead of pointing the operator at an arbitrary
     * cohort label that isn't actually flagged.
     *
     * @param  array<int, CohortMetric>  $breakdowns
     */
    private function worstCohort(array $breakdowns, float $disparityScore, float $threshold): ?string
    {
        if ($breakdowns === [] || $disparityScore <= $threshold) {
            return null;
        }

        $values = array_map(static fn (CohortMetric $c): float => $c->value, $breakdowns);
        $median = $this->median($values);

        $worst = null;
        $worstDistance = -1.0;
        foreach ($breakdowns as $cohort) {
            $distance = abs($cohort->value - $median);
            if ($distance > $worstDistance) {
                $worstDistance = $distance;
                $worst = $cohort->cohort;
            }
        }

        return $worst;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1
            ? $values[$mid]
            : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
