<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\BiasMonitoring\Metrics\CalibrationMetric;
use Padosoft\AiActCompliance\Tests\TestCase;

class CalibrationMetricTest extends TestCase
{
    public function test_well_calibrated_cohorts_produce_low_disparity(): void
    {
        $metric = new CalibrationMetric();

        // mean(score) ≈ mean(label) in both cohorts.
        $result = $metric->computeResult([
            'cohort_dimension' => 'country',
            'observations' => [
                ['cohort' => 'it', 'score' => 0.5, 'label' => 1],
                ['cohort' => 'it', 'score' => 0.5, 'label' => 0],
                ['cohort' => 'en', 'score' => 0.5, 'label' => 1],
                ['cohort' => 'en', 'score' => 0.5, 'label' => 0],
            ],
        ]);

        self::assertSame(0.0, $result->disparityScore);
    }

    public function test_miscalibrated_cohort_drives_disparity(): void
    {
        $metric = new CalibrationMetric();

        $result = $metric->computeResult([
            'cohort_dimension' => 'country',
            'observations' => [
                // it: confident-positive, but the labels are mostly negative
                ['cohort' => 'it', 'score' => 0.9, 'label' => 0],
                ['cohort' => 'it', 'score' => 0.9, 'label' => 0],
                ['cohort' => 'it', 'score' => 0.9, 'label' => 0],
                // en: well-calibrated
                ['cohort' => 'en', 'score' => 0.5, 'label' => 1],
                ['cohort' => 'en', 'score' => 0.5, 'label' => 0],
            ],
        ]);

        self::assertGreaterThan(0.5, $result->disparityScore);
    }

    public function test_observations_missing_score_or_label_are_skipped(): void
    {
        $metric = new CalibrationMetric();

        $result = $metric->computeResult([
            'cohort_dimension' => 'country',
            'observations' => [
                ['cohort' => 'it', 'score' => 0.5, 'label' => 1],
                ['cohort' => 'it', 'score' => 0.5], // skipped — no label
                ['cohort' => 'it', 'label' => 1], // skipped — no score
            ],
        ]);

        // After skips, cohort 'it' has 1 observation; cohort dimension
        // still resolves; disparity stays 0 because there's only one
        // cohort.
        self::assertSame('calibration', $result->metricName);
        self::assertSame(0.0, $result->disparityScore);
    }
}
