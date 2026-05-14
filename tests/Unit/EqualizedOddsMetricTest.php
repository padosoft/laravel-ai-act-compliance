<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\BiasMonitoring\Metrics\EqualizedOddsMetric;
use Padosoft\AiActCompliance\Tests\TestCase;

class EqualizedOddsMetricTest extends TestCase
{
    public function test_perfect_tpr_and_fpr_parity_yields_zero_disparity(): void
    {
        $metric = new EqualizedOddsMetric();

        // Identical TPR + FPR per cohort.
        $result = $metric->computeResult([
            'cohort_dimension' => 'gender',
            'observations' => [
                ['cohort' => 'f', 'prediction' => 1, 'label' => 1],
                ['cohort' => 'f', 'prediction' => 0, 'label' => 0],
                ['cohort' => 'm', 'prediction' => 1, 'label' => 1],
                ['cohort' => 'm', 'prediction' => 0, 'label' => 0],
            ],
        ]);

        self::assertSame(0.0, $result->disparityScore);
    }

    public function test_tpr_gap_drives_disparity_score(): void
    {
        $metric = new EqualizedOddsMetric();

        // Cohort F: TPR=1.0 / FPR=0   (compound 0.5).
        // Cohort M: TPR=0   / FPR=0.5 (compound 0.25).
        $result = $metric->computeResult([
            'cohort_dimension' => 'gender',
            'observations' => [
                ['cohort' => 'f', 'prediction' => 1, 'label' => 1],
                ['cohort' => 'f', 'prediction' => 1, 'label' => 1],
                ['cohort' => 'm', 'prediction' => 0, 'label' => 1],
                ['cohort' => 'm', 'prediction' => 0, 'label' => 1],
                ['cohort' => 'm', 'prediction' => 1, 'label' => 0],
                ['cohort' => 'm', 'prediction' => 0, 'label' => 0],
            ],
        ]);

        self::assertGreaterThan(0.0, $result->disparityScore);
    }

    public function test_cohort_with_only_one_label_class_does_not_crash(): void
    {
        $metric = new EqualizedOddsMetric();

        $result = $metric->computeResult([
            'cohort_dimension' => 'gender',
            'observations' => [
                ['cohort' => 'f', 'prediction' => 1, 'label' => 1],
                ['cohort' => 'm', 'prediction' => 1, 'label' => 1],
            ],
        ]);

        self::assertSame('equalized_odds', $result->metricName);
    }
}
