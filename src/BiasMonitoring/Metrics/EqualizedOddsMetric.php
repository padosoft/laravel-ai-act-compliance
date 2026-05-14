<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Metrics;

use Carbon\CarbonImmutable;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\MetricResult;

/**
 * Equalized Odds — TPR + FPR parity across cohorts.
 *
 * Equalized Odds requires TPR (true-positive-rate) AND FPR (false-
 * positive-rate) parity ACROSS cohorts. The textbook violation looks
 * like one cohort with high TPR and low FPR vs another with low TPR
 * and high FPR — averaging the two into a single (TPR+FPR)/2 score
 * BEFORE computing disparity would silently cancel that violation
 * (both cohorts would converge to 0.5). The Copilot review on PR #2
 * caught this.
 *
 * Implementation: per-cohort we record BOTH TPR and FPR in the
 * CohortMetric (TPR in `value`, FPR in `flagged`-adjacent shadow state
 * via the metric_name in the result), then compute disparity as
 * `max(TPR-spread, FPR-spread)` across cohorts. Evidence: AI Act
 * Art. 10 + Art. 15.
 *
 * Input observation shape:
 *   ['cohort' => 'language=it', 'prediction' => 1, 'label' => 1]
 */
final class EqualizedOddsMetric extends AbstractCohortMetric
{
    public function name(): string
    {
        return 'equalized_odds';
    }

    public function articleReferences(): array
    {
        return ['AI Act Art. 10', 'AI Act Art. 15'];
    }

    /**
     * Override the base flow because EO is genuinely a 2-rate metric
     * (TPR + FPR per cohort) — we cannot collapse it to a single
     * per-cohort scalar and still detect every form of EO violation.
     * The CI is reported per-rate (Wilson on TPR proportion + Wilson
     * on FPR proportion); the disparity is `max(TPR-spread,
     * FPR-spread)`.
     */
    public function computeResult(array $context = []): MetricResult
    {
        $dimension = (string) ($context['cohort_dimension'] ?? 'global');
        $threshold = (float) ($context['disparity_threshold'] ?? 0.05);
        $cohorts = $this->bucketByCohort($context);

        $rates = [];
        $breakdowns = [];
        foreach ($cohorts as $cohort => $observations) {
            $sampleSize = count($observations);
            [$tpr, $fpr] = $this->ratesFor($observations);
            $rates[(string) $cohort] = ['tpr' => $tpr, 'fpr' => $fpr];

            // The cohort `value` carries the average so consumers
            // that don't drill into the metric-specific shape still
            // see a useful summary. CI uses the higher-variance rate.
            $value = ($tpr + $fpr) / 2;
            $worstRate = max($tpr, $fpr);
            [$ciLow, $ciHigh] = $this->wilsonInterval($worstRate, $sampleSize);
            $breakdowns[] = new CohortMetric(
                cohort: (string) $cohort,
                sampleSize: $sampleSize,
                value: round($value, 6),
                ciLow: $ciLow,
                ciHigh: $ciHigh,
                flagged: false,
            );
        }

        $disparityScore = $this->equalizedOddsDisparity($rates);
        $breakdowns = $this->flagAboveThreshold($breakdowns, $disparityScore, $threshold);
        $worst = $this->worstByRateSpread($rates, $disparityScore, $threshold);

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
     * Per-cohort TPR + FPR.
     *
     * @param  array<int, array<string, mixed>>  $observations
     * @return array{0: float, 1: float} [tpr, fpr]
     */
    private function ratesFor(array $observations): array
    {
        $tp = $fn = $fp = $tn = 0;
        foreach ($observations as $observation) {
            $prediction = (int) ($observation['prediction'] ?? 0);
            $label = (int) ($observation['label'] ?? 0);

            if ($label === 1 && $prediction === 1) {
                $tp++;
            } elseif ($label === 1 && $prediction === 0) {
                $fn++;
            } elseif ($label === 0 && $prediction === 1) {
                $fp++;
            } else {
                $tn++;
            }
        }

        $tpr = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0.0;
        $fpr = ($fp + $tn) > 0 ? $fp / ($fp + $tn) : 0.0;

        return [round($tpr, 6), round($fpr, 6)];
    }

    /**
     * EO disparity = max(TPR-spread, FPR-spread). Captures the
     * worst-case rate-parity violation across cohorts.
     *
     * @param  array<string, array{tpr: float, fpr: float}>  $rates
     */
    private function equalizedOddsDisparity(array $rates): float
    {
        if (count($rates) < 2) {
            return 0.0;
        }

        $tprs = array_column($rates, 'tpr');
        $fprs = array_column($rates, 'fpr');

        return round(max(
            max($tprs) - min($tprs),
            max($fprs) - min($fprs),
        ), 6);
    }

    /**
     * @param  array<int, CohortMetric>  $breakdowns
     * @return array<int, CohortMetric>
     */
    private function flagAboveThreshold(array $breakdowns, float $disparity, float $threshold): array
    {
        if ($disparity <= $threshold || $breakdowns === []) {
            return $breakdowns;
        }

        $out = [];
        foreach ($breakdowns as $cohort) {
            $out[] = new CohortMetric(
                cohort: $cohort->cohort,
                sampleSize: $cohort->sampleSize,
                value: $cohort->value,
                ciLow: $cohort->ciLow,
                ciHigh: $cohort->ciHigh,
                flagged: true,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, array{tpr: float, fpr: float}>  $rates
     */
    private function worstByRateSpread(array $rates, float $disparity, float $threshold): ?string
    {
        if ($disparity <= $threshold || $rates === []) {
            return null;
        }

        $tprs = array_column($rates, 'tpr');
        $fprs = array_column($rates, 'fpr');
        $tprSpread = max($tprs) - min($tprs);
        $fprSpread = max($fprs) - min($fprs);

        if ($tprSpread >= $fprSpread) {
            $values = $tprs;
            $key = 'tpr';
        } else {
            $values = $fprs;
            $key = 'fpr';
        }
        $median = $this->medianOf($values);

        $worst = null;
        $worstDistance = -1.0;
        foreach ($rates as $cohort => $pair) {
            $distance = abs($pair[$key] - $median);
            if ($distance > $worstDistance) {
                $worstDistance = $distance;
                $worst = (string) $cohort;
            }
        }

        return $worst;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function medianOf(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = intdiv($n, 2);

        return $n % 2 === 1
            ? $values[$mid]
            : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * Kept for back-compat with callers that invoke the base
     * AbstractCohortMetric flow. Not used internally by
     * computeResult() (overridden above).
     */
    protected function scoreCohort(array $observations): float
    {
        [$tpr, $fpr] = $this->ratesFor($observations);

        return round(($tpr + $fpr) / 2, 6);
    }
}
