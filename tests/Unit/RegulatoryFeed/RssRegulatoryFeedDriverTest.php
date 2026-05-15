<?php

namespace Padosoft\AiActCompliance\Tests\Unit\RegulatoryFeed;

use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\RegulatoryFeed\Drivers\RssRegulatoryFeedDriver;
use Padosoft\AiActCompliance\RegulatoryFeed\Exceptions\RegulatoryFeedFetchException;
use Padosoft\AiActCompliance\Tests\TestCase;

class RssRegulatoryFeedDriverTest extends TestCase
{
    public function test_parses_rss_2_0_with_guid_and_pubdate(): void
    {
        Http::fake([
            'https://eur-lex.example.test/feed.xml' => Http::response($this->rssBody(), 200),
        ]);

        $entries = (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/feed.xml',
        ]);

        self::assertCount(2, $entries);
        self::assertSame('guid-001', $entries[0]->externalId);
        self::assertSame('Amendment to Art. 5', $entries[0]->title);
        self::assertSame('https://example.test/a5', $entries[0]->sourceUrl);
        self::assertNotNull($entries[0]->publishedAt);
    }

    public function test_parses_atom_1_0_with_id_and_updated(): void
    {
        Http::fake([
            'https://eur-lex.example.test/atom.xml' => Http::response($this->atomBody(), 200),
        ]);

        $entries = (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/atom.xml',
        ]);

        self::assertCount(1, $entries);
        self::assertSame('atom-urn-1', $entries[0]->externalId);
        self::assertSame('Updated Art. 27 guidance', $entries[0]->title);
    }

    public function test_missing_guid_falls_back_to_sha256_of_link_and_title(): void
    {
        $rss = '<?xml version="1.0"?><rss><channel>'
            .'<item><title>No GUID entry</title><link>https://example.test/x</link></item>'
            .'</channel></rss>';
        Http::fake([
            'https://eur-lex.example.test/no-guid.xml' => Http::response($rss, 200),
        ]);

        $entries = (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/no-guid.xml',
        ]);

        self::assertCount(1, $entries);
        self::assertSame(64, strlen($entries[0]->externalId), 'sha256 hex is 64 chars');
    }

    public function test_non_2xx_response_throws_fetch_exception(): void
    {
        Http::fake([
            'https://eur-lex.example.test/down.xml' => Http::response('Service Unavailable', 503),
        ]);

        $this->expectException(RegulatoryFeedFetchException::class);
        $this->expectExceptionMessageMatches('/HTTP 503/');

        (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/down.xml',
        ]);
    }

    public function test_missing_feed_url_throws(): void
    {
        $this->expectException(RegulatoryFeedFetchException::class);
        $this->expectExceptionMessageMatches('/feed_url/');

        (new RssRegulatoryFeedDriver())->fetch([]);
    }

    public function test_unrecognised_root_element_throws(): void
    {
        Http::fake([
            'https://eur-lex.example.test/json.xml' => Http::response('<unknown/>', 200),
        ]);

        $this->expectException(RegulatoryFeedFetchException::class);
        $this->expectExceptionMessageMatches('/neither RSS nor Atom/');

        (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/json.xml',
        ]);
    }

    public function test_invalid_xml_body_throws(): void
    {
        Http::fake([
            'https://eur-lex.example.test/broken.xml' => Http::response('not <xml at all', 200),
        ]);

        $this->expectException(RegulatoryFeedFetchException::class);
        $this->expectExceptionMessageMatches('/not valid XML/');

        (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/broken.xml',
        ]);
    }

    public function test_xxe_payload_does_not_resolve_external_entity(): void
    {
        // LIBXML_NONET defends against XXE — the parser must NOT
        // expand the external entity into a local file's contents.
        // Without the defense, $entries[0]->title would contain the
        // contents of /etc/passwd. Copilot iter-1 review on PR #4
        // requested an explicit regression so future flag changes
        // cannot silently re-introduce the attack surface.
        $xxe = '<?xml version="1.0"?>'
            .'<!DOCTYPE rss [ <!ENTITY xxe SYSTEM "file:///etc/passwd"> ]>'
            .'<rss><channel>'
            .'<item><title>&xxe;</title>'
            .'<link>https://example.test/x</link>'
            .'<guid>xxe-guid</guid></item>'
            .'</channel></rss>';
        Http::fake([
            'https://eur-lex.example.test/xxe.xml' => Http::response($xxe, 200),
        ]);

        $entries = (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/xxe.xml',
        ]);

        // Either the entry parses with a literally-empty title (entity
        // unresolved) OR the strict parser drops the item. Both are
        // acceptable — the FORBIDDEN outcome is `root:x:0:0:...`
        // appearing in the title. Always assert at least once so the
        // test is not flagged as risky on parser variants that drop
        // the entry entirely.
        if ($entries === []) {
            self::assertSame([], $entries, 'entry dropped by strict parser is acceptable');
        } else {
            self::assertStringNotContainsString('root:', $entries[0]->title);
            self::assertStringNotContainsString('/bin/', $entries[0]->title);
        }
    }

    public function test_atom_prefers_alternate_link_over_self(): void
    {
        // Atom entries can have rel="self" (feed API URL) + rel="alternate"
        // (human-readable amendment page). We want the alternate.
        $atom = '<?xml version="1.0"?>'
            .'<feed xmlns="http://www.w3.org/2005/Atom">'
            .'<entry>'
            .'<id>atom-multi-link</id>'
            .'<title>Multi-link entry</title>'
            .'<link href="https://feed.example.test/self" rel="self"/>'
            .'<link href="https://example.test/human-page" rel="alternate"/>'
            .'<updated>2026-05-01T10:00:00Z</updated>'
            .'</entry>'
            .'</feed>';
        Http::fake([
            'https://eur-lex.example.test/multi.xml' => Http::response($atom, 200),
        ]);

        $entries = (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/multi.xml',
        ]);

        self::assertCount(1, $entries);
        self::assertSame('https://example.test/human-page', $entries[0]->sourceUrl);
    }

    public function test_zero_max_entries_returns_empty_set(): void
    {
        // Predictable cap: 0 means "no entries", not "no cap".
        // Copilot iter-1 review on PR #4.
        Http::fake([
            'https://eur-lex.example.test/zero.xml' => Http::response(
                $this->rssBody(),
                200,
            ),
        ]);

        $entries = (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/zero.xml',
            'max_entries_per_poll' => 0,
        ]);

        self::assertSame([], $entries);
    }

    public function test_max_entries_per_poll_caps_the_result_set(): void
    {
        $items = '';
        for ($i = 0; $i < 10; $i++) {
            $items .= "<item><title>Item $i</title><link>https://example.test/$i</link><guid>g-$i</guid></item>";
        }
        $rss = "<?xml version=\"1.0\"?><rss><channel>$items</channel></rss>";
        Http::fake([
            'https://eur-lex.example.test/cap.xml' => Http::response($rss, 200),
        ]);

        $entries = (new RssRegulatoryFeedDriver())->fetch([
            'feed_url' => 'https://eur-lex.example.test/cap.xml',
            'max_entries_per_poll' => 3,
        ]);

        self::assertCount(3, $entries);
    }

    private function rssBody(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>EU AI Act amendments</title>
    <item>
      <title>Amendment to Art. 5</title>
      <link>https://example.test/a5</link>
      <description>Clarifies prohibited practices.</description>
      <guid>guid-001</guid>
      <pubDate>Wed, 01 May 2026 10:00:00 +0000</pubDate>
    </item>
    <item>
      <title>Amendment to Art. 27</title>
      <link>https://example.test/a27</link>
      <description>FRIA template update.</description>
      <guid>guid-002</guid>
      <pubDate>Thu, 02 May 2026 11:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;
    }

    private function atomBody(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>EU AI Act amendments</title>
  <entry>
    <id>atom-urn-1</id>
    <title>Updated Art. 27 guidance</title>
    <link href="https://example.test/atom-art27"/>
    <summary>FRIA refinements.</summary>
    <updated>2026-05-01T10:00:00Z</updated>
  </entry>
</feed>
XML;
    }
}
