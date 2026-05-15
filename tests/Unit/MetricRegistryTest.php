<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\NamedCohortMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Exceptions\InvalidMetricBindingException;
use Padosoft\AiActCompliance\BiasMonitoring\Exceptions\UnknownMetricException;
use Padosoft\AiActCompliance\BiasMonitoring\Metrics\CalibrationMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Metrics\DemographicParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Metrics\EqualizedOddsMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Services\MetricRegistry;
use Padosoft\AiActCompliance\Tests\TestCase;

class MetricRegistryTest extends TestCase
{
    public function test_default_metrics_are_seeded_from_config(): void
    {
        $registry = $this->app->make(MetricRegistry::class);

        self::assertTrue($registry->has('demographic_parity'));
        self::assertTrue($registry->has('equalized_odds'));
        self::assertTrue($registry->has('calibration'));
    }

    public function test_resolve_returns_the_concrete_metric_for_the_registered_name(): void
    {
        $registry = $this->app->make(MetricRegistry::class);

        self::assertInstanceOf(DemographicParityMetric::class, $registry->resolve('demographic_parity'));
        self::assertInstanceOf(EqualizedOddsMetric::class, $registry->resolve('equalized_odds'));
        self::assertInstanceOf(CalibrationMetric::class, $registry->resolve('calibration'));
    }

    public function test_resolve_caches_the_instance_for_repeated_calls(): void
    {
        $registry = $this->app->make(MetricRegistry::class);

        $a = $registry->resolve('demographic_parity');
        $b = $registry->resolve('demographic_parity');

        self::assertSame($a, $b);
    }

    public function test_unknown_metric_resolution_throws_a_typed_exception(): void
    {
        $this->expectException(UnknownMetricException::class);

        $registry = $this->app->make(MetricRegistry::class);
        $registry->resolve('this-metric-does-not-exist');
    }

    public function test_registering_a_non_implementing_fqcn_is_rejected_at_register_time(): void
    {
        $this->expectException(InvalidMetricBindingException::class);

        // \stdClass does not implement NamedCohortMetric — R23 boot guard.
        $registry = $this->app->make(MetricRegistry::class);
        $registry->register('rogue', \stdClass::class);
    }

    public function test_duplicate_registration_is_rejected(): void
    {
        $this->expectException(InvalidMetricBindingException::class);

        $registry = $this->app->make(MetricRegistry::class);
        // 'demographic_parity' is already seeded from config — registering
        // it again with even the same FQCN must fail.
        $registry->register('demographic_parity', DemographicParityMetric::class);
    }

    public function test_registering_a_host_app_custom_metric_is_resolvable(): void
    {
        $registry = $this->app->make(MetricRegistry::class);
        $registry->register('custom_fixture', FixtureCustomMetric::class);

        self::assertTrue($registry->has('custom_fixture'));
        self::assertInstanceOf(FixtureCustomMetric::class, $registry->resolve('custom_fixture'));
    }
}

class FixtureCustomMetric implements NamedCohortMetric
{
    public function compute(array $context = []): array
    {
        return [];
    }

    public function name(): string
    {
        return 'custom_fixture';
    }

    public function articleReferences(): array
    {
        return ['AI Act Art. 10'];
    }

    public function version(): string
    {
        return '1.0';
    }
}
