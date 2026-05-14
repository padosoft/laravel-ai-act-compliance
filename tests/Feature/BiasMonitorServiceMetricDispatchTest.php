<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Exceptions\UnknownMetricException;
use Padosoft\AiActCompliance\BiasMonitoring\Metrics\DemographicParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Models\BiasSnapshot;
use Padosoft\AiActCompliance\BiasMonitoring\Services\BiasMonitorService;
use Padosoft\AiActCompliance\BiasMonitoring\Services\MetricRegistry;
use Padosoft\AiActCompliance\Tests\TestCase;

class BiasMonitorServiceMetricDispatchTest extends TestCase
{
    public function test_capture_resolves_metric_via_registry_when_metric_name_in_context(): void
    {
        $service = new BiasMonitorService(
            metric: new FixtureBareLegacyMetric(),
            registry: $this->app->make(MetricRegistry::class),
        );

        $snapshot = $service->capture([
            'metric_name' => 'equalized_odds',
            'cohort_dimension' => 'gender',
            'observations' => [
                ['cohort' => 'f', 'prediction' => 1, 'label' => 1],
                ['cohort' => 'm', 'prediction' => 0, 'label' => 0],
            ],
        ]);

        self::assertSame('equalized_odds', $snapshot->metric_name);
        self::assertSame('gender', $snapshot->cohort_dimension);
        self::assertNotNull($snapshot->article_evidence_json);
        self::assertContains('AI Act Art. 10', $snapshot->article_evidence_json);
    }

    public function test_capture_falls_back_to_injected_metric_when_metric_name_is_absent(): void
    {
        $service = new BiasMonitorService(
            metric: new DemographicParityMetric(),
            registry: $this->app->make(MetricRegistry::class),
        );

        $snapshot = $service->capture([
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 0],
            ],
        ]);

        self::assertSame('demographic_parity', $snapshot->metric_name);
    }

    public function test_unknown_metric_name_falls_back_to_the_injected_metric(): void
    {
        // Defensive: the service must NOT throw when the registry doesn't
        // know the name; instead, it falls back to the legacy injected
        // metric so v1.1 callers keep working. The registry would have
        // already raised at boot for misconfigured metric maps.
        $service = new BiasMonitorService(
            metric: new DemographicParityMetric(),
            registry: $this->app->make(MetricRegistry::class),
        );

        $snapshot = $service->capture([
            'metric_name' => 'not-registered',
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
            ],
        ]);

        self::assertSame('demographic_parity', $snapshot->metric_name);
    }

    public function test_legacy_injected_metric_returning_array_still_persists_via_legacy_path(): void
    {
        $service = new BiasMonitorService(
            metric: new FixtureBareLegacyMetric(),
            registry: null,
        );

        $snapshot = $service->capture([]);

        self::assertSame('legacy-cohort', $snapshot->cohort);
        self::assertSame(0.42, (float) $snapshot->score);
        // Legacy path doesn't populate metric_name (column default is
        // 'demographic_parity').
        self::assertSame('demographic_parity', $snapshot->metric_name);
    }

    public function test_unknown_metric_exception_is_typed(): void
    {
        $this->expectException(UnknownMetricException::class);

        $registry = $this->app->make(MetricRegistry::class);
        $registry->resolve('never-registered');
    }
}

class FixtureBareLegacyMetric implements CohortParityMetric
{
    public function compute(array $context = []): array
    {
        return [
            'cohort' => 'legacy-cohort',
            'score' => 0.42,
            'delta' => 0.01,
        ];
    }
}
