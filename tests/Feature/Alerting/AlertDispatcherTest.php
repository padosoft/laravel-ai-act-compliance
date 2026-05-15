<?php

namespace Padosoft\AiActCompliance\Tests\Feature\Alerting;

use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Padosoft\AiActCompliance\Alerting\Models\AlertDispatch;
use Padosoft\AiActCompliance\Alerting\Models\AlertRoute;
use Padosoft\AiActCompliance\Alerting\Services\AlertDispatcher;
use Padosoft\AiActCompliance\Tests\TestCase;

class AlertDispatcherTest extends TestCase
{
    private function payload(string $tenantId = 'tenant-a'): AlertPayload
    {
        return new AlertPayload(
            severity: 'high',
            title: 'Bias drift on demographic_parity',
            body: 'Disparity 0.18',
            tenantId: $tenantId,
            evidenceUrl: 'https://example.test/bias',
            metricName: 'demographic_parity',
            cohort: 'language=it',
            articles: ['AI Act Art. 10'],
        );
    }

    public function test_slack_route_dispatched_when_present_and_email_cc_recorded(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);
        \Illuminate\Support\Facades\Mail::fake();

        AlertRoute::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/services/x',
            'enabled' => true,
        ]);
        AlertRoute::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'email',
            'email' => 'dpo@example.test',
            'enabled' => true,
        ]);

        $rows = $this->app->make(AlertDispatcher::class)->dispatch($this->payload());

        self::assertCount(2, $rows);
        self::assertSame('slack', $rows[0]->channel);
        self::assertTrue($rows[0]->ok);
        self::assertSame('email', $rows[1]->channel);
    }

    public function test_disabled_slack_route_is_skipped_in_favour_of_discord(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('should not be called', 500),
            'https://discord.com/*' => Http::response(null, 204),
        ]);

        AlertRoute::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/x',
            'enabled' => false,
        ]);
        AlertRoute::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'discord',
            'webhook_url' => 'https://discord.com/api/webhooks/1/abc',
            'enabled' => true,
        ]);

        $rows = $this->app->make(AlertDispatcher::class)->dispatch($this->payload());

        self::assertCount(1, $rows);
        self::assertSame('discord', $rows[0]->channel);
        self::assertTrue($rows[0]->ok);
    }

    public function test_tripped_channel_writes_skipped_audit_row_and_falls_through_to_discord(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('boom', 500),
            'https://discord.com/*' => Http::response(null, 204),
        ]);

        // Slack route pre-tripped with cooldown 1 hour in future.
        AlertRoute::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/x',
            'enabled' => true,
            'consecutive_failures' => 5,
            'tripped_until' => now()->addHour(),
        ]);
        AlertRoute::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'discord',
            'webhook_url' => 'https://discord.com/api/webhooks/1/abc',
            'enabled' => true,
        ]);

        $rows = $this->app->make(AlertDispatcher::class)->dispatch($this->payload());

        // 2 rows: 1 \"skipped\" on slack + 1 \"ok\" on discord.
        self::assertCount(2, $rows);
        $slack = $rows[0];
        self::assertSame('slack', $slack->channel);
        self::assertFalse($slack->ok);
        self::assertStringContainsString('tripped', $slack->error_message);

        $discord = $rows[1];
        self::assertSame('discord', $discord->channel);
        self::assertTrue($discord->ok);
    }

    public function test_throttled_repeat_dispatch_writes_no_new_row(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        AlertRoute::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/x',
            'enabled' => true,
        ]);

        $dispatcher = $this->app->make(AlertDispatcher::class);

        $first = $dispatcher->dispatch($this->payload());
        $second = $dispatcher->dispatch($this->payload());

        // First dispatch creates one row; second is throttled away.
        self::assertCount(1, $first);
        self::assertCount(0, $second);

        self::assertSame(1, AlertDispatch::query()->count());
    }
}
