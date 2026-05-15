<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\MetricResult;
use Padosoft\AiActCompliance\BiasMonitoring\Metrics\DemographicParityMetric;
use Padosoft\AiActCompliance\Tests\TestCase;

class DemographicParityMetricTest extends TestCase
{
    public function test_even_cohorts_produce_zero_disparity(): void
    {
        $metric = new DemographicParityMetric();

        $result = $metric->computeResult([
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 0],
                ['cohort' => 'en', 'prediction' => 1],
                ['cohort' => 'en', 'prediction' => 0],
            ],
        ]);

        self::assertInstanceOf(MetricResult::class, $result);
        self::assertSame('demographic_parity', $result->metricName);
        self::assertSame(0.0, $result->disparityScore);
    }

    public function test_skewed_cohorts_produce_non_zero_disparity(): void
    {
        $metric = new DemographicParityMetric();

        $result = $metric->computeResult([
            'cohort_dimension' => 'language',
            'disparity_threshold' => 0.05,
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'en', 'prediction' => 0],
                ['cohort' => 'en', 'prediction' => 0],
                ['cohort' => 'en', 'prediction' => 0],
                ['cohort' => 'en', 'prediction' => 0],
            ],
        ]);

        self::assertGreaterThan(0.5, $result->disparityScore);
        self::assertContains($result->worstCohort, ['it', 'en']);
    }

    public function test_single_cohort_yields_zero_disparity_and_no_worst(): void
    {
        $metric = new DemographicParityMetric();

        $result = $metric->computeResult([
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 0],
            ],
        ]);

        self::assertSame(0.0, $result->disparityScore);
    }

    public function test_article_evidence_is_recorded(): void
    {
        $metric = new DemographicParityMetric();
        $result = $metric->computeResult(['observations' => []]);

        self::assertContains('AI Act Art. 10', $result->articleEvidence);
        self::assertContains('AI Act Art. 15', $result->articleEvidence);
    }
}
