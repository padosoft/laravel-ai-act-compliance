<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\RiskRegister\Enums\AiActRiskCategory;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\AiActCompliance\RiskRegister\Services\RiskRegisterService;
use Padosoft\AiActCompliance\Tests\TestCase;

class RiskRegisterServiceTest extends TestCase
{
    public function test_create_persists_a_minimal_risk_entry(): void
    {
        $entry = (new RiskRegisterService())->create([
            'name' => 'Hallucinated legal advice',
            'category' => AiActRiskCategory::HIGH->value,
            'status' => 'open',
        ])->fresh();

        self::assertNotNull($entry);
        self::assertSame('Hallucinated legal advice', $entry->name);
        self::assertSame('high', $entry->category);
        self::assertSame('open', $entry->status);
    }

    public function test_create_persists_article_references_as_json_array(): void
    {
        $entry = (new RiskRegisterService())->create([
            'name' => 'CV screening for HR partner',
            'category' => AiActRiskCategory::HIGH->value,
            'status' => 'in_progress',
            'article_refs' => ['AI Act Annex III §4', 'AI Act Art. 14'],
        ])->fresh();

        self::assertIsArray($entry->article_refs);
        self::assertContains('AI Act Annex III §4', $entry->article_refs);
        self::assertContains('AI Act Art. 14', $entry->article_refs);
    }

    public function test_each_risk_category_round_trips_through_the_enum(): void
    {
        $service = new RiskRegisterService();

        foreach (AiActRiskCategory::cases() as $category) {
            $entry = $service->create([
                'name' => "Risk for {$category->value}",
                'category' => $category->value,
                'status' => 'open',
            ])->fresh();
            self::assertSame($category->value, $entry->category);
        }

        self::assertSame(
            count(AiActRiskCategory::cases()),
            RiskRegisterEntry::query()->count(),
        );
    }

    public function test_risks_can_be_filtered_by_category(): void
    {
        $service = new RiskRegisterService();
        $service->create(['name' => 'A', 'category' => AiActRiskCategory::HIGH->value, 'status' => 'open']);
        $service->create(['name' => 'B', 'category' => AiActRiskCategory::HIGH->value, 'status' => 'closed']);
        $service->create(['name' => 'C', 'category' => AiActRiskCategory::LIMITED->value, 'status' => 'open']);

        self::assertSame(2, RiskRegisterEntry::query()->where('category', 'high')->count());
        self::assertSame(1, RiskRegisterEntry::query()->where('category', 'limited')->count());
    }

    public function test_risks_can_be_filtered_by_status(): void
    {
        $service = new RiskRegisterService();
        $service->create(['name' => 'Open A', 'category' => AiActRiskCategory::HIGH->value, 'status' => 'open']);
        $service->create(['name' => 'Open B', 'category' => AiActRiskCategory::LOW->value, 'status' => 'open']);
        $service->create(['name' => 'Closed', 'category' => AiActRiskCategory::HIGH->value, 'status' => 'closed']);

        self::assertSame(2, RiskRegisterEntry::query()->where('status', 'open')->count());
        self::assertSame(1, RiskRegisterEntry::query()->where('status', 'closed')->count());
    }

    public function test_each_risk_status_label_is_distinct(): void
    {
        $statuses = ['open', 'in_progress', 'closed'];
        $service = new RiskRegisterService();
        foreach ($statuses as $status) {
            $service->create([
                'name' => "Risk {$status}",
                'category' => AiActRiskCategory::HIGH->value,
                'status' => $status,
            ]);
        }

        foreach ($statuses as $status) {
            self::assertSame(1, RiskRegisterEntry::query()->where('status', $status)->count());
        }
    }

    public function test_unacceptable_risks_are_recorded_with_audit_trail(): void
    {
        $entry = (new RiskRegisterService())->create([
            'name' => 'Biometric categorisation in support tickets',
            'category' => AiActRiskCategory::UNACCEPTABLE->value,
            'status' => 'closed',
            'article_refs' => ['AI Act Art. 5'],
        ])->fresh();

        self::assertSame('unacceptable', $entry->category);
        self::assertSame('closed', $entry->status);
        self::assertContains('AI Act Art. 5', $entry->article_refs);
    }
}
