<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\BiasMonitoring\Metrics\EqualizedOddsMetric;
use Padosoft\AiActCompliance\Tests\TestCase;

class EqualizedOddsMetricTest extends TestCase
{
    public function test_opposite_tpr_fpr_violation_is_detected_not_cancelled(): void
    {
        // Regression for Copilot review PR #2: a cohort with TPR=1/FPR=0
        // averages to 0.5; another with TPR=0/FPR=1 also averages to 0.5.
        // If the metric averaged BEFORE computing disparity, the disparity
        // score would be 0 and miss the maximal violation. With the
        // separate TPR / FPR spread comparison, the disparity is 1.0.
        $metric = new EqualizedOddsMetric();

        $result = $metric->computeResult([
            'cohort_dimension' => 'gender',
            'observations' => [
                // Cohort A: TPR=1, FPR=0
                ['cohort' => 'a', 'prediction' => 1, 'label' => 1],
                ['cohort' => 'a', 'prediction' => 1, 'label' => 1],
                ['cohort' => 'a', 'prediction' => 0, 'label' => 0],
                ['cohort' => 'a', 'prediction' => 0, 'label' => 0],
                // Cohort B: TPR=0, FPR=1
                ['cohort' => 'b', 'prediction' => 0, 'label' => 1],
                ['cohort' => 'b', 'prediction' => 0, 'label' => 1],
                ['cohort' => 'b', 'prediction' => 1, 'label' => 0],
                ['cohort' => 'b', 'prediction' => 1, 'label' => 0],
            ],
        ]);

        self::assertSame(1.0, $result->disparityScore);
        self::assertNotNull($result->worstCohort);
    }

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
