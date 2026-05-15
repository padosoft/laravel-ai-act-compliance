<?php

namespace Padosoft\AiActCompliance\Tests\Unit\Alerting;

use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\Alerting\Channels\SlackWebhookChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Padosoft\AiActCompliance\Tests\TestCase;

class SlackWebhookChannelTest extends TestCase
{
    private function payload(): AlertPayload
    {
        return new AlertPayload(
            severity: 'high',
            title: 'Bias drift',
            body: 'Disparity 0.18 on it cohort.',
            tenantId: 'tenant-a',
            evidenceUrl: 'https://example.test/bias',
            metricName: 'demographic_parity',
            cohort: 'language=it',
            articles: ['AI Act Art. 10', 'AI Act Art. 15'],
        );
    }

    public function test_happy_path_returns_success_on_200(): void
    {
        Http::fake(['https://hooks.slack.com/services/foo' => Http::response('ok', 200)]);

        $result = (new SlackWebhookChannel())->send(
            $this->payload(),
            'https://hooks.slack.com/services/foo',
        );

        self::assertTrue($result->ok);
        self::assertFalse($result->transient);
        self::assertSame(200, $result->httpStatus);
    }

    public function test_429_classified_as_transient_failure(): void
    {
        Http::fake(['https://hooks.slack.com/*' => Http::response('rate limited', 429)]);

        $result = (new SlackWebhookChannel())->send(
            $this->payload(),
            'https://hooks.slack.com/services/foo',
        );

        self::assertFalse($result->ok);
        self::assertTrue($result->transient);
        self::assertSame(429, $result->httpStatus);
    }

    public function test_5xx_classified_as_transient_failure(): void
    {
        Http::fake(['https://hooks.slack.com/*' => Http::response('boom', 503)]);

        $result = (new SlackWebhookChannel())->send(
            $this->payload(),
            'https://hooks.slack.com/services/foo',
        );

        self::assertFalse($result->ok);
        self::assertTrue($result->transient);
        self::assertSame(503, $result->httpStatus);
    }

    public function test_4xx_other_than_429_is_permanent_failure(): void
    {
        Http::fake(['https://hooks.slack.com/*' => Http::response('bad payload', 400)]);

        $result = (new SlackWebhookChannel())->send(
            $this->payload(),
            'https://hooks.slack.com/services/foo',
        );

        self::assertFalse($result->ok);
        self::assertFalse($result->transient);
        self::assertSame(400, $result->httpStatus);
    }

    public function test_network_error_classified_as_transient_failure(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('DNS failure');
        });

        $result = (new SlackWebhookChannel())->send(
            $this->payload(),
            'https://hooks.slack.com/services/foo',
        );

        self::assertFalse($result->ok);
        self::assertTrue($result->transient);
        self::assertNull($result->httpStatus);
    }
}
