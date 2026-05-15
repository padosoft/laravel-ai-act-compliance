<?php

namespace Padosoft\AiActCompliance\Tests\Unit\Alerting;

use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\Alerting\Channels\DiscordWebhookChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Padosoft\AiActCompliance\Tests\TestCase;

class DiscordWebhookChannelTest extends TestCase
{
    private function payload(): AlertPayload
    {
        return new AlertPayload(
            severity: 'critical',
            title: 'Bias drift',
            body: 'Disparity 0.25 on age_band cohort.',
            tenantId: 'tenant-b',
            evidenceUrl: 'https://example.test/bias',
            metricName: 'equalized_odds',
            cohort: 'age_band=60+',
        );
    }

    public function test_discord_204_no_content_is_treated_as_success(): void
    {
        Http::fake(['https://discord.com/*' => Http::response(null, 204)]);

        $result = (new DiscordWebhookChannel())->send(
            $this->payload(),
            'https://discord.com/api/webhooks/123/abc',
        );

        self::assertTrue($result->ok);
        self::assertSame(204, $result->httpStatus);
    }

    public function test_5xx_is_transient_failure(): void
    {
        Http::fake(['https://discord.com/*' => Http::response('upstream', 502)]);

        $result = (new DiscordWebhookChannel())->send(
            $this->payload(),
            'https://discord.com/api/webhooks/123/abc',
        );

        self::assertFalse($result->ok);
        self::assertTrue($result->transient);
    }

    public function test_429_is_transient_failure(): void
    {
        Http::fake(['https://discord.com/*' => Http::response('rate limited', 429)]);

        $result = (new DiscordWebhookChannel())->send(
            $this->payload(),
            'https://discord.com/api/webhooks/123/abc',
        );

        self::assertTrue($result->transient);
    }

    public function test_400_is_permanent_failure(): void
    {
        Http::fake(['https://discord.com/*' => Http::response('invalid webhook payload', 400)]);

        $result = (new DiscordWebhookChannel())->send(
            $this->payload(),
            'https://discord.com/api/webhooks/123/abc',
        );

        self::assertFalse($result->ok);
        self::assertFalse($result->transient);
    }

    public function test_network_error_is_transient_failure(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('connection refused');
        });

        $result = (new DiscordWebhookChannel())->send(
            $this->payload(),
            'https://discord.com/api/webhooks/123/abc',
        );

        self::assertTrue($result->transient);
    }
}
