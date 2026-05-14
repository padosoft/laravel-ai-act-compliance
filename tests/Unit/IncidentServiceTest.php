<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\Incident\Enums\IncidentSeverity;
use Padosoft\AiActCompliance\Incident\Enums\IncidentStatus;
use Padosoft\AiActCompliance\Incident\Models\IncidentStateTransition;
use Padosoft\AiActCompliance\Incident\Models\IncidentTicket;
use Padosoft\AiActCompliance\Incident\Services\IncidentService;
use Padosoft\AiActCompliance\Tests\TestCase;

class IncidentServiceTest extends TestCase
{
    public function test_open_creates_ticket_with_default_open_status(): void
    {
        $ticket = (new IncidentService())->open([
            'title' => 'Hallucination on legal queries',
            'severity' => IncidentSeverity::HIGH->value,
        ])->fresh();

        self::assertSame('open', $ticket->status);
        self::assertSame('high', $ticket->severity);
    }

    public function test_open_records_the_initial_state_transition_with_null_from(): void
    {
        $ticket = (new IncidentService())->open([
            'title' => 'PII leak in chat logs',
            'severity' => IncidentSeverity::CRITICAL->value,
        ])->fresh();

        $transition = IncidentStateTransition::query()
            ->where('incident_ticket_id', $ticket->id)
            ->first();

        self::assertNotNull($transition);
        self::assertNull($transition->from_status);
        self::assertSame('open', $transition->to_status);
        self::assertNotNull($transition->transitioned_at);
    }

    public function test_open_persists_each_severity_distinctly(): void
    {
        $service = new IncidentService();
        foreach (IncidentSeverity::cases() as $severity) {
            $service->open([
                'title' => "Incident for {$severity->value}",
                'severity' => $severity->value,
            ]);
        }

        foreach (IncidentSeverity::cases() as $severity) {
            self::assertSame(
                1,
                IncidentTicket::query()->where('severity', $severity->value)->count(),
                "Expected exactly one ticket at severity {$severity->value}",
            );
        }
    }

    public function test_each_open_creates_exactly_one_state_transition_row(): void
    {
        $service = new IncidentService();
        for ($i = 0; $i < 5; $i++) {
            $service->open([
                'title' => "Ticket #{$i}",
                'severity' => IncidentSeverity::MEDIUM->value,
            ]);
        }

        self::assertSame(5, IncidentTicket::query()->count());
        self::assertSame(5, IncidentStateTransition::query()->count());
    }

    public function test_status_enum_covers_the_four_lifecycle_states(): void
    {
        $statuses = collect(IncidentStatus::cases())->pluck('value')->all();
        self::assertContains('open', $statuses);
        self::assertContains('triage', $statuses);
        self::assertContains('mitigating', $statuses);
        self::assertContains('closed', $statuses);
        self::assertCount(4, $statuses);
    }

    public function test_severity_enum_covers_the_four_levels(): void
    {
        $severities = collect(IncidentSeverity::cases())->pluck('value')->all();
        self::assertContains('low', $severities);
        self::assertContains('medium', $severities);
        self::assertContains('high', $severities);
        self::assertContains('critical', $severities);
        self::assertCount(4, $severities);
    }

    public function test_open_persists_article_references_when_provided(): void
    {
        $ticket = (new IncidentService())->open([
            'title' => 'Bias drift on IT cohort',
            'severity' => IncidentSeverity::HIGH->value,
            'article_refs' => ['AI Act Art. 10', 'AI Act Art. 15'],
        ])->fresh();

        self::assertIsArray($ticket->article_refs);
        self::assertContains('AI Act Art. 10', $ticket->article_refs);
        self::assertContains('AI Act Art. 15', $ticket->article_refs);
    }
}
