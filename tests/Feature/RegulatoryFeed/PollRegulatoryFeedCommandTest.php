<?php

namespace Padosoft\AiActCompliance\Tests\Feature\RegulatoryFeed;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\RegulatoryFeed\Models\RegulatoryAmendment;
use Padosoft\AiActCompliance\Tests\TestCase;

class PollRegulatoryFeedCommandTest extends TestCase
{
    public function test_command_skips_when_feature_disabled(): void
    {
        config()->set('ai-act-compliance.regulatory_feed.enabled', false);

        $code = Artisan::call('ai-act:regulatory-poll');

        self::assertSame(0, $code);
        self::assertSame(0, RegulatoryAmendment::query()->count());
    }

    public function test_command_ingests_when_feature_enabled(): void
    {
        config()->set('ai-act-compliance.regulatory_feed.enabled', true);
        config()->set('ai-act-compliance.regulatory_feed.sources.eu-ai-act-rss.feed_url', 'https://feed.example.test/v1');
        Http::fake([
            'https://feed.example.test/*' => Http::response(
                '<?xml version="1.0"?><rss><channel>'
                .'<item><title>Art. 9 risk management update</title><link>https://example.test/a9</link><guid>cmd-guid-1</guid></item>'
                .'</channel></rss>',
                200,
            ),
        ]);

        $code = Artisan::call('ai-act:regulatory-poll');

        self::assertSame(0, $code);
        self::assertSame(1, RegulatoryAmendment::query()->count());
    }

    public function test_command_returns_failure_exit_code_when_driver_errors(): void
    {
        config()->set('ai-act-compliance.regulatory_feed.enabled', true);
        config()->set('ai-act-compliance.regulatory_feed.sources.eu-ai-act-rss.feed_url', 'https://feed.example.test/v1');
        Http::fake([
            'https://feed.example.test/*' => Http::response('upstream-down', 502),
        ]);

        $code = Artisan::call('ai-act:regulatory-poll');

        self::assertNotSame(0, $code);
    }
}
