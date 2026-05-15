<?php

namespace Padosoft\AiActCompliance\Tests\Feature\RegulatoryFeed;

use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentSeverity;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentStatus;
use Padosoft\AiActCompliance\RegulatoryFeed\Models\RegulatoryAmendment;
use Padosoft\AiActCompliance\Tests\TestCase;

class RegulatoryAmendmentApiTest extends TestCase
{
    private function makeAmendment(array $overrides = []): RegulatoryAmendment
    {
        return RegulatoryAmendment::query()->create(array_merge([
            'source_driver' => 'eu-ai-act-rss',
            'external_id' => 'fixture-'.bin2hex(random_bytes(4)),
            'source_url' => 'https://example.test/x',
            'title' => 'Sample amendment',
            'summary' => null,
            'body' => null,
            'impacted_clauses_json' => [],
            'status' => RegulatoryAmendmentStatus::Pending->value,
            'severity' => RegulatoryAmendmentSeverity::Medium->value,
            'ingested_at' => now(),
        ], $overrides));
    }

    public function test_index_lists_amendments_with_filter(): void
    {
        $this->makeAmendment(['severity' => 'critical', 'title' => 'CRIT-A']);
        $this->makeAmendment(['severity' => 'low', 'title' => 'LOW-B']);

        $response = $this->getJson('/api/admin/ai-act-compliance/regulatory-amendments?severity=critical');

        $response->assertOk();
        $payload = $response->json('data.data');
        self::assertNotNull($payload);
        self::assertSame(1, count($payload));
        self::assertSame('CRIT-A', $payload[0]['title']);
    }

    public function test_update_triage_sets_triaged_at_on_first_status_change(): void
    {
        $row = $this->makeAmendment(['status' => 'pending']);
        self::assertNull($row->triaged_at);

        $response = $this->patchJson(
            '/api/admin/ai-act-compliance/regulatory-amendments/'.$row->id,
            ['status' => 'triaged', 'triaged_by' => 'dpo@example.test'],
        );

        $response->assertOk();
        $row->refresh();
        self::assertSame('triaged', $row->status);
        self::assertNotNull($row->triaged_at);
        self::assertSame('dpo@example.test', $row->triaged_by);
    }

    public function test_update_validates_severity_against_enum(): void
    {
        $row = $this->makeAmendment();

        $response = $this->patchJson(
            '/api/admin/ai-act-compliance/regulatory-amendments/'.$row->id,
            ['severity' => 'NUCLEAR'],
        );

        $response->assertStatus(422);
    }

    public function test_poll_endpoint_returns_409_when_feature_disabled(): void
    {
        config()->set('ai-act-compliance.regulatory_feed.enabled', false);

        $response = $this->postJson('/api/admin/ai-act-compliance/regulatory-amendments/poll');

        $response->assertStatus(409);
    }

    public function test_poll_endpoint_returns_summary_when_enabled(): void
    {
        config()->set('ai-act-compliance.regulatory_feed.enabled', true);
        config()->set('ai-act-compliance.regulatory_feed.sources.eu-ai-act-rss.feed_url', 'https://feed.example.test/v1');
        Http::fake([
            'https://feed.example.test/*' => Http::response(
                '<?xml version="1.0"?><rss><channel>'
                .'<item><title>Art. 5 update</title><link>https://example.test/a5</link><guid>api-guid</guid></item>'
                .'</channel></rss>',
                200,
            ),
        ]);

        $response = $this->postJson('/api/admin/ai-act-compliance/regulatory-amendments/poll');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['ingested', 'skipped', 'failures']]);
        self::assertSame(1, $response->json('data.ingested'));
    }
}
