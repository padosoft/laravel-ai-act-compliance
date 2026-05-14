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

    public function test_explicit_unknown_metric_name_throws_loudly(): void
    {
        // v1.2 — Copilot review on PR #2 commit 19d2a6a flagged the
        // earlier silent-fallback behaviour as a typo trap. The
        // service must now throw UnknownMetricException when the
        // caller explicitly names a metric the registry doesn't know,
        // matching the boot-time R23 loud-fail stance.
        $this->expectException(UnknownMetricException::class);

        $service = new BiasMonitorService(
            metric: new DemographicParityMetric(),
            registry: $this->app->make(MetricRegistry::class),
        );

        $service->capture([
            'metric_name' => 'not-registered',
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
            ],
        ]);
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
        // v1.2 — A bare CohortParityMetric (does NOT implement
        // NamedCohortMetric) is recorded as 'legacy' so the audit
        // trail surfaces the unknown provenance without misattributing
        // it to 'demographic_parity'. Copilot review on PR #2.
        self::assertSame('legacy', $snapshot->metric_name);
    }

    public function test_legacy_path_named_metric_records_its_own_name(): void
    {
        // Custom NamedCohortMetric that returns a v1.1 array shape
        // (instead of a MetricResult). The legacy persist path should
        // derive metric_name from the instance, not hard-code one.
        $namedLegacy = new class implements \Padosoft\AiActCompliance\BiasMonitoring\Contracts\NamedCohortMetric
        {
            public function compute(array $context = []): array
            {
                return ['cohort' => 'corp-x', 'score' => 0.5, 'delta' => 0];
            }

            public function name(): string
            {
                return 'host_custom_fairness';
            }

            public function articleReferences(): array
            {
                return ['AI Act Art. 10'];
            }

            public function version(): string
            {
                return '2.1.0';
            }
        };

        $service = new BiasMonitorService(metric: $namedLegacy, registry: null);
        $snapshot = $service->capture([]);

        self::assertSame('host_custom_fairness', $snapshot->metric_name);
        // metric_version is sourced from the metric instance so host
        // metrics can bump their own version on every algorithmic
        // change without touching this package.
        self::assertSame('2.1.0', $snapshot->metric_version);
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
