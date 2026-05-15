<?php

namespace Padosoft\AiActCompliance\Tests\Feature\Alerting;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\Alerting\Events\BiasDriftDetected;
use Padosoft\AiActCompliance\Alerting\Models\AlertDispatch;
use Padosoft\AiActCompliance\Alerting\Models\AlertRoute;
use Padosoft\AiActCompliance\BiasMonitoring\Metrics\DemographicParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Services\BiasMonitorService;
use Padosoft\AiActCompliance\Tests\TestCase;

class BiasDriftAlertFlowTest extends TestCase
{
    public function test_high_disparity_capture_fires_the_bias_drift_event(): void
    {
        config()->set('ai-act-compliance.bias.disparity_threshold', 0.05);
        Event::fake([BiasDriftDetected::class]);

        $service = new BiasMonitorService(
            metric: new DemographicParityMetric(),
            registry: null,
        );

        $service->capture([
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'en', 'prediction' => 0],
                ['cohort' => 'en', 'prediction' => 0],
            ],
        ]);

        Event::assertDispatched(BiasDriftDetected::class, function ($event) {
            return $event->disparityScore > 0.05 && $event->metricName === 'demographic_parity';
        });
    }

    public function test_low_disparity_capture_does_not_fire_the_event(): void
    {
        config()->set('ai-act-compliance.bias.disparity_threshold', 0.05);
        Event::fake([BiasDriftDetected::class]);

        $service = new BiasMonitorService(
            metric: new DemographicParityMetric(),
            registry: null,
        );

        $service->capture([
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 0],
                ['cohort' => 'en', 'prediction' => 1],
                ['cohort' => 'en', 'prediction' => 0],
            ],
        ]);

        Event::assertNotDispatched(BiasDriftDetected::class);
    }

    public function test_end_to_end_drift_dispatches_alert_when_enabled(): void
    {
        config()->set('ai-act-compliance.alerting.enabled', true);
        config()->set('ai-act-compliance.bias.disparity_threshold', 0.05);
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        AlertRoute::query()->create([
            'tenant_id' => null,
            'channel' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/services/foo',
            'enabled' => true,
        ]);

        $service = new BiasMonitorService(
            metric: new DemographicParityMetric(),
            registry: null,
        );

        $service->capture([
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'en', 'prediction' => 0],
                ['cohort' => 'en', 'prediction' => 0],
            ],
        ]);

        $rows = AlertDispatch::query()->where('channel', 'slack')->get();
        self::assertCount(1, $rows);
        self::assertTrue((bool) $rows->first()->ok);
    }

    public function test_drift_with_alerting_disabled_writes_no_dispatch_row(): void
    {
        config()->set('ai-act-compliance.alerting.enabled', false);
        config()->set('ai-act-compliance.bias.disparity_threshold', 0.05);

        AlertRoute::query()->create([
            'tenant_id' => null,
            'channel' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/services/foo',
            'enabled' => true,
        ]);

        $service = new BiasMonitorService(
            metric: new DemographicParityMetric(),
            registry: null,
        );

        $service->capture([
            'cohort_dimension' => 'language',
            'observations' => [
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'it', 'prediction' => 1],
                ['cohort' => 'en', 'prediction' => 0],
                ['cohort' => 'en', 'prediction' => 0],
            ],
        ]);

        self::assertSame(0, AlertDispatch::query()->count());
    }
}
