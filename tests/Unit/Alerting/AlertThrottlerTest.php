<?php

namespace Padosoft\AiActCompliance\Tests\Unit\Alerting;

use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\Alerting\Models\AlertDispatch;
use Padosoft\AiActCompliance\Alerting\Services\AlertThrottler;
use Padosoft\AiActCompliance\Tests\TestCase;

class AlertThrottlerTest extends TestCase
{
    public function test_no_recent_dispatch_should_not_suppress(): void
    {
        $throttler = new AlertThrottler(60);

        self::assertFalse(
            $throttler->shouldSuppress('tenant-a', 'slack', 'language=it'),
        );
    }

    public function test_recent_successful_dispatch_suppresses_a_repeat(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        AlertDispatch::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'slack',
            'severity' => 'high',
            'title' => 'Drift',
            'cohort' => 'language=it',
            'payload_json' => ['cohort' => 'language=it'],
            'ok' => true,
            'transient_failure' => false,
        ]);

        Carbon::setTestNow('2026-08-01 10:30:00');
        $throttler = new AlertThrottler(60);

        self::assertTrue(
            $throttler->shouldSuppress('tenant-a', 'slack', 'language=it'),
        );

        Carbon::setTestNow();
    }

    public function test_dispatch_outside_window_does_not_suppress(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        AlertDispatch::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'slack',
            'severity' => 'high',
            'title' => 'Drift',
            'cohort' => 'language=it',
            'payload_json' => ['cohort' => 'language=it'],
            'ok' => true,
            'transient_failure' => false,
        ]);

        Carbon::setTestNow('2026-08-01 11:30:00'); // > 60 min later
        $throttler = new AlertThrottler(60);

        self::assertFalse(
            $throttler->shouldSuppress('tenant-a', 'slack', 'language=it'),
        );

        Carbon::setTestNow();
    }

    public function test_failed_dispatch_does_not_suppress_retries(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        AlertDispatch::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'slack',
            'severity' => 'high',
            'title' => 'Drift',
            'cohort' => 'language=it',
            'payload_json' => ['cohort' => 'language=it'],
            'ok' => false,
            'transient_failure' => true,
        ]);

        Carbon::setTestNow('2026-08-01 10:30:00');
        $throttler = new AlertThrottler(60);

        // A failed dispatch is NOT a suppression anchor — the
        // throttler only counts successful deliveries inside the
        // window, so the next retry can fire immediately.
        self::assertFalse(
            $throttler->shouldSuppress('tenant-a', 'slack', 'language=it'),
        );

        Carbon::setTestNow();
    }
}
