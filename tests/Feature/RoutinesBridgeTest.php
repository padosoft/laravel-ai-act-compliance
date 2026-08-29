<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\AiActCompliance\Tests\TestCase;
use Padosoft\Routines\Contracts\Consent\RoutineMandate;
use Padosoft\Routines\Contracts\Target\TargetResult;
use Padosoft\Routines\Events\RoutineMandateGranted;
use Padosoft\Routines\Events\RoutinePaused;
use Padosoft\Routines\Events\RoutineResolved;
use Padosoft\Routines\Events\RoutineSuspended;
use Padosoft\Routines\Models\Routine;
use Padosoft\Routines\Models\RoutineRun;

class RoutinesBridgeTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ai-act-compliance.routines.enabled', true);
    }

    /**
     * The models are never saved: the bridge reads them, it does not query them, so the
     * routines migrations do not need to run in this package's test database.
     */
    private function routine(): Routine
    {
        $routine = new Routine;
        $routine->forceFill([
            'id' => 'rt_01TEST',
            'owner' => 'user:42',
            'name' => 'Solleciti fatture scadute',
            'target_type' => 'invoice.reminder',
            'trigger_kind' => 'cron',
            'cron' => '0 3 * * *',
            'timezone' => 'Europe/Rome',
        ]);

        return $routine;
    }

    private function mandate(array $actionClasses = ['invoice.remind']): RoutineMandate
    {
        return new RoutineMandate(
            targetType: 'invoice.reminder',
            payloadDigest: 'sha256:abc123',
            actionClasses: $actionClasses,
            budgetCeiling: 50.0,
            notAfter: new \DateTimeImmutable('2026-12-31 00:00:00'),
            currency: 'EUR',
        );
    }

    private function pausedRun(): RoutineRun
    {
        $run = new RoutineRun;
        $run->forceFill([
            'id' => 'run_01TEST',
            'routine_id' => 'rt_01TEST',
            'outcome' => 'paused',
            'action_class' => 'invoice.write_off',
            'question' => 'Chiudere la fattura INV-003, scaduta da 400 giorni?',
        ]);

        return $run;
    }

    public function test_the_mandate_becomes_an_approved_art14_record_with_its_consent_evidence(): void
    {
        app(Dispatcher::class)->dispatch(new RoutineMandateGranted(
            $this->routine(),
            $this->mandate(),
            confirmationId: 'stepup_xyz789',
            aal: 'aal2',
        ));

        $review = HumanReview::query()->where('subject_type', 'routine_mandate')->firstOrFail();
        $this->assertSame('rt_01TEST', $review->subject_id);
        $this->assertSame('approved', $review->state);
        $this->assertSame('user:42', $review->reviewer_id);
        $this->assertStringContainsString('invoice.remind', $review->review_notes);
        $this->assertStringContainsString('sha256:abc123', $review->review_notes);
        $this->assertStringContainsString('stepup_xyz789', $review->review_notes);
        $this->assertStringContainsString('AAL aal2', $review->review_notes);
        $this->assertStringContainsString('50', $review->review_notes);
    }

    public function test_an_empty_mandate_is_recorded_as_authorising_nothing(): void
    {
        // Fail-closed è la decisione giusta, ma se il record dicesse solo «Action classes: »
        // un revisore leggerebbe un campo vuoto e penserebbe a un dato mancante, non a un
        // mandato che deliberatamente non autorizza niente.
        app(Dispatcher::class)->dispatch(new RoutineMandateGranted($this->routine(), $this->mandate([])));

        $review = HumanReview::query()->where('subject_type', 'routine_mandate')->firstOrFail();
        $this->assertStringContainsString('authorises nothing', $review->review_notes);
    }

    public function test_the_routine_lands_in_the_art6_risk_register_when_the_mandate_is_granted(): void
    {
        app(Dispatcher::class)->dispatch(new RoutineMandateGranted($this->routine(), $this->mandate()));

        $entry = RiskRegisterEntry::query()->firstOrFail();
        $this->assertStringContainsString('[rt_01TEST]', $entry->name);
        $this->assertSame('limited', $entry->category);
        $this->assertSame('open', $entry->status);
        $this->assertSame('user:42', $entry->owner_id);
        $this->assertStringContainsString('0 3 * * *', $entry->description);
        $this->assertContains('AI Act Art. 14', $entry->article_refs);
    }

    public function test_renewing_the_consent_reopens_the_same_entry_instead_of_adding_a_second(): void
    {
        app(Dispatcher::class)->dispatch(new RoutineMandateGranted($this->routine(), $this->mandate()));
        app(Dispatcher::class)->dispatch(new RoutineSuspended($this->routine(), 'budget_exhausted', 'tetto mensile'));
        app(Dispatcher::class)->dispatch(new RoutineMandateGranted($this->routine(), $this->mandate()));

        $this->assertSame(1, RiskRegisterEntry::query()->count());
        $this->assertSame('open', RiskRegisterEntry::query()->firstOrFail()->status);
    }

    public function test_a_pause_opens_a_pending_oversight_item_keyed_by_run(): void
    {
        app(Dispatcher::class)->dispatch(new RoutinePaused(
            $this->routine(),
            $this->pausedRun(),
            TargetResult::paused('Fuori dal mandato'),
        ));

        $review = HumanReview::query()->where('subject_type', 'routine_run')->firstOrFail();
        $this->assertSame('run_01TEST', $review->subject_id);
        $this->assertSame('pending', $review->state);
        $this->assertSame('user:42', $review->reviewer_id);
        $this->assertStringContainsString('invoice.write_off', $review->review_notes);
        $this->assertStringContainsString('INV-003', $review->review_notes);
    }

    public function test_the_human_answer_closes_the_item_and_records_who_answered(): void
    {
        app(Dispatcher::class)->dispatch(new RoutinePaused($this->routine(), $this->pausedRun(), TargetResult::paused('x')));
        app(Dispatcher::class)->dispatch(new RoutineResolved(
            $this->routine(),
            $this->pausedRun(),
            approved: false,
            resolvedBy: 'user:anna',
            note: 'Il cliente ha pagato ieri',
        ));

        $review = HumanReview::query()->where('subject_type', 'routine_run')->firstOrFail();
        $this->assertSame('rejected', $review->state);
        $this->assertSame('user:anna', $review->reviewer_id);
        $this->assertStringContainsString('was not performed', $review->review_notes);
        $this->assertStringContainsString('Il cliente ha pagato ieri', $review->review_notes);
    }

    public function test_an_approval_says_the_run_resumed_not_that_it_succeeded(): void
    {
        // «L'umano ha detto di sì» e «il lavoro è riuscito» sono fatti diversi, e il secondo
        // arriva dopo — a volte molto dopo, a volte non arriva affatto.
        app(Dispatcher::class)->dispatch(new RoutinePaused($this->routine(), $this->pausedRun(), TargetResult::paused('x')));
        app(Dispatcher::class)->dispatch(new RoutineResolved($this->routine(), $this->pausedRun(), true, 'user:anna'));

        $review = HumanReview::query()->where('subject_type', 'routine_run')->firstOrFail();
        $this->assertSame('approved', $review->state);
        $this->assertStringContainsString('resumed from where it stopped', $review->review_notes);
    }

    public function test_an_answer_without_a_recorded_pause_does_not_invent_an_item(): void
    {
        app(Dispatcher::class)->dispatch(new RoutineResolved($this->routine(), $this->pausedRun(), true, 'user:anna'));

        $this->assertSame(0, HumanReview::query()->count());
    }

    public function test_a_suspension_moves_the_register_entry_to_mitigating_with_the_reason(): void
    {
        app(Dispatcher::class)->dispatch(new RoutineMandateGranted($this->routine(), $this->mandate()));
        app(Dispatcher::class)->dispatch(new RoutineSuspended(
            $this->routine(),
            'routine_approval_starvation',
            'sospesa da rebel-ai-guard',
        ));

        $entry = RiskRegisterEntry::query()->firstOrFail();
        $this->assertSame('mitigating', $entry->status);
        $this->assertStringContainsString('routine_approval_starvation', $entry->description);
        $this->assertStringContainsString('rebel-ai-guard', $entry->description);
    }

    public function test_a_suspension_without_a_registered_routine_is_a_no_op(): void
    {
        // Una routine senza mandato non ha una voce nel registro: inventarne una qui
        // registrerebbe la coda di un ciclo di vita senza la sua testa (l'evidenza del consenso).
        app(Dispatcher::class)->dispatch(new RoutineSuspended($this->routine(), 'target_not_registered'));

        $this->assertSame(0, RiskRegisterEntry::query()->count());
    }
}
