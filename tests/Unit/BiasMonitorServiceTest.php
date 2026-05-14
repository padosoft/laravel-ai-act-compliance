<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Models\BiasSnapshot;
use Padosoft\AiActCompliance\BiasMonitoring\Services\BiasMonitorService;
use Padosoft\AiActCompliance\Tests\TestCase;

class BiasMonitorServiceTest extends TestCase
{
    public function test_capture_persists_a_snapshot_with_metric_payload(): void
    {
        $metric = $this->fakeMetric([
            'cohort' => 'IT',
            'score' => 0.82,
            'delta' => 0.06,
            'flagged' => true,
        ]);

        $snapshot = (new BiasMonitorService($metric))->capture(['cohort' => 'IT']);

        self::assertNotNull($snapshot->fresh());
        self::assertSame('IT', $snapshot->cohort);
        self::assertSame(0.82, $snapshot->score);
        self::assertSame(0.06, $snapshot->delta);
        self::assertTrue($snapshot->payload['flagged'] ?? false);
    }

    public function test_capture_defaults_to_global_cohort_when_metric_omits_cohort(): void
    {
        $metric = $this->fakeMetric([
            'score' => 0.91,
            'delta' => 0.0,
        ]);

        $snapshot = (new BiasMonitorService($metric))->capture()->fresh();

        self::assertSame('global', $snapshot->cohort);
        self::assertSame(0.91, $snapshot->score);
    }

    public function test_capture_coerces_score_and_delta_to_floats(): void
    {
        $metric = $this->fakeMetric([
            'cohort' => 'language:DE',
            // Pretend the metric returned strings (eval-harness JSON deserialised)
            'score' => '0.78',
            'delta' => '-0.04',
        ]);

        $snapshot = (new BiasMonitorService($metric))->capture()->fresh();

        self::assertIsFloat($snapshot->score);
        self::assertIsFloat($snapshot->delta);
        self::assertEqualsWithDelta(0.78, $snapshot->score, 0.001);
        self::assertEqualsWithDelta(-0.04, $snapshot->delta, 0.001);
    }

    public function test_capture_creates_one_snapshot_per_invocation(): void
    {
        $metric = $this->fakeMetric(['cohort' => 'API', 'score' => 0.88, 'delta' => 0.01]);
        $service = new BiasMonitorService($metric);

        $service->capture();
        $service->capture();
        $service->capture();

        self::assertSame(3, BiasSnapshot::query()->count());
    }

    public function test_capture_forwards_context_to_the_metric(): void
    {
        $received = null;
        $metric = new class($received) implements CohortParityMetric {
            public function __construct(private mixed &$received) {}
            public function compute(array $context = []): array
            {
                $this->received = $context;
                return ['cohort' => 'X', 'score' => 1.0, 'delta' => 0.0];
            }
        };

        (new BiasMonitorService($metric))->capture([
            'cohort' => 'IT',
            'window_days' => 7,
            'baseline' => 0.95,
        ]);

        self::assertIsArray($received);
        self::assertSame('IT', $received['cohort'] ?? null);
        self::assertSame(7, $received['window_days'] ?? null);
    }

    private function fakeMetric(array $payload): CohortParityMetric
    {
        return new class($payload) implements CohortParityMetric {
            public function __construct(private readonly array $payload) {}
            public function compute(array $context = []): array
            {
                return $this->payload;
            }
        };
    }
}
