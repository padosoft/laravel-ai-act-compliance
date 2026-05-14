<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Metrics;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortMetric;

/**
 * Equalized Odds — TPR + FPR parity across cohorts.
 *
 * Per-cohort statistic is the average of true-positive-rate and
 * false-positive-rate (the textbook Equalized Odds compound score).
 * Disparity is the max spread of that compound score. Evidence for
 * AI Act Art. 10 + Art. 15.
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

    protected function scoreCohort(array $observations): float
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

        return round(($tpr + $fpr) / 2, 6);
    }
}
