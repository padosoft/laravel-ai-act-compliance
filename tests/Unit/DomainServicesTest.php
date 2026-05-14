<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Carbon\CarbonImmutable;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Services\BiasMonitorService;
use Padosoft\AiActCompliance\ComplianceAttestation\Services\ComplianceAttestationService;
use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\AiActCompliance\HumanReviewTracker\Services\HumanReviewService;
use Padosoft\AiActCompliance\Incident\Enums\IncidentSeverity;
use Padosoft\AiActCompliance\Incident\Enums\IncidentStatus;
use Padosoft\AiActCompliance\Incident\Models\IncidentStateTransition;
use Padosoft\AiActCompliance\Incident\Services\IncidentService;
use Padosoft\AiActCompliance\RiskRegister\Enums\AiActRiskCategory;
use Padosoft\AiActCompliance\RiskRegister\Services\RiskRegisterService;
use Padosoft\AiActCompliance\Tests\TestCase;

class DomainServicesTest extends TestCase
{
    public function test_risk_register_service_persists_article_references_as_arrays(): void
    {
        $entry = (new RiskRegisterService())->create([
            'name' => 'Biometric classifier',
            'category' => AiActRiskCategory::HIGH->value,
            'status' => 'open',
            'article_refs' => ['9', '10'],
        ])->fresh();

        self::assertSame(['9', '10'], $entry->article_refs);
    }

    public function test_incident_service_opens_a_ticket_and_records_the_initial_transition(): void
    {
        $ticket = (new IncidentService())->open([
            'title' => 'Unexpected refusal rate',
            'severity' => IncidentSeverity::HIGH->value,
            'description' => 'Spike in denials for one cohort.',
        ])->fresh();

        $transition = IncidentStateTransition::query()->first();

        self::assertSame(IncidentStatus::OPEN->value, $ticket->status);
        self::assertNotNull($transition);
        self::assertSame(IncidentStatus::OPEN->value, $transition->to_status);
        self::assertInstanceOf(CarbonImmutable::class, $transition->transitioned_at);
    }

    public function test_human_review_service_transitions_reviews(): void
    {
        $review = HumanReview::query()->create([
            'subject_type' => 'decision',
            'subject_id' => '42',
            'state' => HumanReviewState::PENDING->value,
        ]);

        $updated = (new HumanReviewService())->transition($review, HumanReviewState::APPROVED);

        self::assertSame(HumanReviewState::APPROVED->value, $updated->state);
    }

    public function test_compliance_attestation_service_persists_payloads_with_array_casts(): void
    {
        $attestation = (new ComplianceAttestationService())->create([
            'attestation_type' => 'annual-review',
            'status' => 'published',
            'payload' => ['scope' => 'credit-risk'],
        ])->fresh();

        self::assertSame(['scope' => 'credit-risk'], $attestation->payload);
    }

    public function test_bias_monitor_service_uses_the_bound_metric_contract(): void
    {
        $this->app->instance(CohortParityMetric::class, new class implements CohortParityMetric
        {
            public function compute(array $context = []): array
            {
                return [
                    'cohort' => 'group-a',
                    'score' => 0.91,
                    'delta' => 0.07,
                    'inputs' => $context,
                ];
            }
        });

        $snapshot = $this->app->make(BiasMonitorService::class)->capture(['source' => 'nightly-job'])->fresh();

        self::assertSame('group-a', $snapshot->cohort);
        self::assertSame(0.91, $snapshot->score);
        self::assertSame(0.07, $snapshot->delta);
        self::assertSame(['cohort' => 'group-a', 'score' => 0.91, 'delta' => 0.07, 'inputs' => ['source' => 'nightly-job']], $snapshot->payload);
    }
}
