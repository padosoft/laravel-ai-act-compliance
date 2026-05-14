<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\FRIA\Enums\FriaStatus;
use Padosoft\AiActCompliance\FRIA\Models\FriaAssessment;
use Padosoft\AiActCompliance\FRIA\Services\FriaService;
use Padosoft\AiActCompliance\Tests\TestCase;

class FriaServiceTest extends TestCase
{
    public function test_open_persists_a_draft_assessment_with_config_defaults(): void
    {
        config()->set('ai-act-compliance.fria.default_review_cadence_days', 180);

        $assessment = (new FriaService())->open([
            'title' => 'Hiring assistant FRIA',
            'scope' => 'CV screening for HR partner deployments',
            'opened_by' => 'compliance-officer@example.test',
        ])->fresh();

        self::assertNotNull($assessment);
        self::assertSame('Hiring assistant FRIA', $assessment->title);
        self::assertSame(FriaStatus::DRAFT->value, $assessment->status);
        self::assertSame(180, $assessment->review_cadence_days);
        self::assertNull($assessment->next_review_at);
    }

    public function test_schedule_review_computes_cadence_from_config_default(): void
    {
        config()->set('ai-act-compliance.fria.default_review_cadence_days', 90);
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

        $service = new FriaService();
        $assessment = $service->open([
            'title' => 'Default-cadence FRIA',
            'scope' => 'Testing the config default',
        ]);

        $service->scheduleReview($assessment);
        $assessment->refresh();

        self::assertSame(FriaStatus::ACTIVE->value, $assessment->status);
        self::assertNotNull($assessment->next_review_at);
        self::assertSame('2026-08-12 10:00:00', $assessment->next_review_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_schedule_review_with_explicit_days_overrides_config_default(): void
    {
        config()->set('ai-act-compliance.fria.default_review_cadence_days', 180);
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

        $service = new FriaService();
        $assessment = $service->open([
            'title' => 'Override-cadence FRIA',
            'scope' => 'Explicit override wins',
        ]);

        $service->scheduleReview($assessment, 30);
        $assessment->refresh();

        self::assertSame(30, $assessment->review_cadence_days);
        self::assertSame('2026-06-13 10:00:00', $assessment->next_review_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_sign_off_records_signer_and_timestamp(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-14 12:30:00'));

        $service = new FriaService();
        $assessment = $service->open([
            'title' => 'Sign-off FRIA',
            'scope' => 'Compliance officer formally accepts',
        ]);

        $service->signOff($assessment, 'dpo@example.test');
        $assessment->refresh();

        self::assertSame('dpo@example.test', $assessment->signed_off_by);
        self::assertNotNull($assessment->signed_off_at);
        self::assertSame('2026-05-14 12:30:00', $assessment->signed_off_at->toDateTimeString());
        self::assertSame(FriaStatus::ACTIVE->value, $assessment->status);

        Carbon::setTestNow();
    }

    public function test_retire_transitions_to_retired_state(): void
    {
        $service = new FriaService();
        $assessment = $service->open([
            'title' => 'Retire FRIA',
            'scope' => 'Deprecated deployment scope',
        ]);

        $service->retire($assessment);
        $assessment->refresh();

        self::assertSame(FriaStatus::RETIRED->value, $assessment->status);
    }

    public function test_update_mitigations_does_not_touch_risks_payload(): void
    {
        $service = new FriaService();
        $assessment = $service->open([
            'title' => 'Mitigation isolation FRIA',
            'scope' => 'Mitigations must not overwrite risks_json',
            'risks_json' => ['hallucination', 'bias_geographic'],
        ]);

        $service->updateMitigations($assessment, [
            'human_review_threshold' => 0.7,
            'rate_limit_per_user' => 50,
        ]);

        $assessment->refresh();

        self::assertSame(['hallucination', 'bias_geographic'], $assessment->risks_json);
        self::assertIsArray($assessment->mitigations_json);
        self::assertArrayHasKey('human_review_threshold', $assessment->mitigations_json);
    }

    public function test_is_review_due_returns_true_when_next_review_at_is_in_the_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

        $assessment = FriaAssessment::query()->create([
            'title' => 'Overdue FRIA',
            'scope' => 'Past review date',
            'review_cadence_days' => 90,
            'next_review_at' => Carbon::parse('2026-04-01 10:00:00'),
            'status' => FriaStatus::ACTIVE->value,
        ]);

        self::assertTrue((new FriaService())->isReviewDue($assessment));

        Carbon::setTestNow();
    }

    public function test_is_review_due_returns_false_when_no_review_scheduled(): void
    {
        $assessment = (new FriaService())->open([
            'title' => 'Draft FRIA',
            'scope' => 'No review yet',
        ]);

        self::assertFalse((new FriaService())->isReviewDue($assessment));
    }
}
