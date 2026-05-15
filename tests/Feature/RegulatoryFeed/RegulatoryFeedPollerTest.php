<?php

namespace Padosoft\AiActCompliance\Tests\Feature\RegulatoryFeed;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentSeverity;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentStatus;
use Padosoft\AiActCompliance\RegulatoryFeed\Events\RegulatoryAmendmentDetected;
use Padosoft\AiActCompliance\RegulatoryFeed\Models\RegulatoryAmendment;
use Padosoft\AiActCompliance\RegulatoryFeed\Services\RegulatoryFeedPoller;
use Padosoft\AiActCompliance\Tests\TestCase;

class RegulatoryFeedPollerTest extends TestCase
{
    private function feedXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Amendment to Art. 9 risk management system</title>
      <link>https://example.test/a9</link>
      <description>Continuous risk monitoring obligations clarified.</description>
      <guid>poll-guid-art9</guid>
      <pubDate>Wed, 01 May 2026 10:00:00 +0000</pubDate>
    </item>
    <item>
      <title>FRIA template revised under Art. 27</title>
      <link>https://example.test/a27</link>
      <description>Fundamental rights impact assessment template.</description>
      <guid>poll-guid-art27</guid>
      <pubDate>Thu, 02 May 2026 11:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;
    }

    public function test_poll_ingests_new_amendments_and_fires_event(): void
    {
        Event::fake([RegulatoryAmendmentDetected::class]);
        Http::fake([
            'https://feed.example.test/*' => Http::response($this->feedXml(), 200),
        ]);
        config()->set('ai-act-compliance.regulatory_feed.sources.eu-ai-act-rss.feed_url', 'https://feed.example.test/v1');

        $result = $this->app->make(RegulatoryFeedPoller::class)->poll();

        self::assertSame(2, $result['ingested']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([], $result['failures']);
        self::assertSame(2, RegulatoryAmendment::query()->count());

        Event::assertDispatchedTimes(RegulatoryAmendmentDetected::class, 2);

        $art9 = RegulatoryAmendment::query()->where('external_id', 'poll-guid-art9')->firstOrFail();
        self::assertSame(RegulatoryAmendmentStatus::Pending->value, $art9->status);
        self::assertSame(RegulatoryAmendmentSeverity::Critical->value, $art9->severity);
        self::assertContains('AI Act Art. 9', $art9->impacted_clauses_json);

        $art27 = RegulatoryAmendment::query()->where('external_id', 'poll-guid-art27')->firstOrFail();
        self::assertSame(RegulatoryAmendmentSeverity::High->value, $art27->severity);
        self::assertContains('AI Act Art. 27', $art27->impacted_clauses_json);
    }

    public function test_re_polling_same_feed_is_idempotent(): void
    {
        Http::fake([
            'https://feed.example.test/*' => Http::response($this->feedXml(), 200),
        ]);
        config()->set('ai-act-compliance.regulatory_feed.sources.eu-ai-act-rss.feed_url', 'https://feed.example.test/v1');

        $first = $this->app->make(RegulatoryFeedPoller::class)->poll();
        $second = $this->app->make(RegulatoryFeedPoller::class)->poll();

        self::assertSame(2, $first['ingested']);
        self::assertSame(0, $second['ingested']);
        self::assertSame(2, $second['skipped']);
        self::assertSame(2, RegulatoryAmendment::query()->count());
    }

    public function test_feed_fetch_failure_is_collected_per_driver(): void
    {
        Http::fake([
            'https://feed.example.test/*' => Http::response('boom', 502),
        ]);
        config()->set('ai-act-compliance.regulatory_feed.sources.eu-ai-act-rss.feed_url', 'https://feed.example.test/v1');

        $result = $this->app->make(RegulatoryFeedPoller::class)->poll();

        self::assertSame(0, $result['ingested']);
        self::assertArrayHasKey('eu-ai-act-rss', $result['failures']);
        self::assertStringContainsString('HTTP 502', $result['failures']['eu-ai-act-rss']);
        self::assertSame(0, RegulatoryAmendment::query()->count());
    }

    public function test_invalid_driver_fqcn_reports_failure_without_crashing(): void
    {
        // Driver FQCN is validated at POLL time (not at boot — the SP
        // does not pre-flight the drivers config) so a misconfigured
        // host sees the error on the first poll, not at startup.
        config()->set('ai-act-compliance.regulatory_feed.drivers', [
            'bogus' => 'Padosoft\\AiActCompliance\\NotARealDriver',
        ]);

        $result = $this->app->make(RegulatoryFeedPoller::class)->poll();

        self::assertSame(0, $result['ingested']);
        self::assertArrayHasKey('bogus', $result['failures']);
    }

    public function test_per_driver_isolation_catches_arbitrary_throwables(): void
    {
        // A custom driver that throws a plain Exception (not a
        // RegulatoryFeedFetchException) must STILL be caught — the
        // poll keeps going for other drivers. Copilot iter-1 review
        // PR #4.
        config()->set('ai-act-compliance.regulatory_feed.drivers', [
            'throwing' => \Padosoft\AiActCompliance\Tests\Feature\RegulatoryFeed\Fixtures\AlwaysThrowingDriver::class,
        ]);

        $result = $this->app->make(RegulatoryFeedPoller::class)->poll();

        self::assertSame(0, $result['ingested']);
        self::assertArrayHasKey('throwing', $result['failures']);
        self::assertStringContainsString('custom driver boom', $result['failures']['throwing']);
    }

    public function test_upstream_values_exceeding_column_limits_are_truncated(): void
    {
        // 600-char title + 200-char externalId — both exceed the
        // migration's bounded columns. The poller MUST truncate
        // rather than crashing the whole run.
        $longTitle = str_repeat('A', 600);
        $longGuid = str_repeat('g', 200);
        Http::fake([
            'https://feed.example.test/*' => Http::response(
                '<?xml version="1.0"?><rss><channel>'
                .'<item><title>'.$longTitle.'</title>'
                .'<link>https://example.test/long</link>'
                .'<guid>'.$longGuid.'</guid></item>'
                .'</channel></rss>',
                200,
            ),
        ]);
        config()->set('ai-act-compliance.regulatory_feed.sources.eu-ai-act-rss.feed_url', 'https://feed.example.test/v1');

        $result = $this->app->make(RegulatoryFeedPoller::class)->poll();

        self::assertSame(1, $result['ingested']);
        $row = RegulatoryAmendment::query()->first();
        self::assertSame(500, mb_strlen($row->title));
        self::assertSame(191, mb_strlen($row->external_id));
    }
}
