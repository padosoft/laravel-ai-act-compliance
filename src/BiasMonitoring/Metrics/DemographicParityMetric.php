<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Metrics;

/**
 * Demographic Parity — P(prediction = positive | cohort) across cohorts.
 *
 * The default v1.2 reference metric. Triggers when the positive-rate
 * spread across cohorts exceeds `bias.disparity_threshold`. Evidence
 * for AI Act Art. 10 + Art. 15.
 *
 * Input observation shape:
 *   ['cohort' => 'language=it', 'prediction' => 1, ...]
 */
final class DemographicParityMetric extends AbstractCohortMetric
{
    public function name(): string
    {
        return 'demographic_parity';
    }

    public function articleReferences(): array
    {
        return ['AI Act Art. 10', 'AI Act Art. 15'];
    }

    protected function scoreCohort(array $observations): float
    {
        if ($observations === []) {
            return 0.0;
        }

        $positive = 0;
        foreach ($observations as $observation) {
            $prediction = $observation['prediction'] ?? 0;
            if ((int) $prediction === 1) {
                $positive++;
            }
        }

        return round($positive / count($observations), 6);
    }
}
