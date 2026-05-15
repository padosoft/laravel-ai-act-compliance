<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Metrics;

/**
 * Calibration-by-cohort — |mean(score) − mean(label)| per cohort.
 *
 * Tests whether the model's predicted probability matches the empirical
 * positive-rate within each cohort. A well-calibrated model has a score
 * near 0 in every cohort. The disparity gap surfaces cohorts where the
 * model is mis-calibrated. Evidence for AI Act Art. 15.
 *
 * Input observation shape:
 *   ['cohort' => 'language=it', 'score' => 0.78, 'label' => 1]
 *
 * `score` is the predicted probability ∈ [0, 1]; `label` is the
 * ground-truth (0 or 1). Observations missing either field are skipped.
 */
final class CalibrationMetric extends AbstractCohortMetric
{
    public function name(): string
    {
        return 'calibration';
    }

    public function articleReferences(): array
    {
        return ['AI Act Art. 15'];
    }

    protected function scoreCohort(array $observations): float
    {
        $scoreSum = 0.0;
        $labelSum = 0;
        $count = 0;

        foreach ($observations as $observation) {
            if (! array_key_exists('score', $observation) || ! array_key_exists('label', $observation)) {
                continue;
            }
            $scoreSum += (float) $observation['score'];
            $labelSum += (int) $observation['label'];
            $count++;
        }

        if ($count === 0) {
            return 0.0;
        }

        $meanScore = $scoreSum / $count;
        $meanLabel = $labelSum / $count;

        return round(abs($meanScore - $meanLabel), 6);
    }
}
