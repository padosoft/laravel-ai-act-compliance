<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\AiActCompliance\Tests\TestCase;
use Padosoft\Iam\Agents\Events\AgentApproved;
use Padosoft\Iam\Agents\Events\AgentRetired;
use Padosoft\Iam\Agents\Events\AgentSuspended;
use Padosoft\Iam\Agents\Events\DelegationGrantCreated;
use Padosoft\Iam\Agents\Events\DelegationGrantRevoked;
use Padosoft\Iam\Contracts\Assurance\Aal;
use Padosoft\Iam\Contracts\Delegation\DelegationBudget;
use Padosoft\Iam\Contracts\Delegation\DelegationGrant;
use Padosoft\Iam\Contracts\Delegation\DelegationGrantStatus;
use Padosoft\Iam\Contracts\Support\SubjectRef;

class IamDelegationBridgeTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ai-act-compliance.iam_delegation.enabled', true);
    }

    private function grant(bool $revoked = false, ?DelegationBudget $budget = null): DelegationGrant
    {
        return new DelegationGrant(
            id: 'dgr_01TEST',
            user: new SubjectRef('user', '42'),
            agent: new SubjectRef('agent', 'agt_x'),
            scopes: ['orders:read', 'orders:write'],
            purpose: 'Order assistance',
            status: $revoked ? DelegationGrantStatus::Revoked : DelegationGrantStatus::Active,
            expiresAt: new \DateTimeImmutable('2026-09-01 00:00:00'),
            createdAt: new \DateTimeImmutable('2026-08-24 00:00:00'),
            consentConfirmationId: 'stepup_abc123',
            consentAal: Aal::AAL2,
            revokedAt: $revoked ? new \DateTimeImmutable('2026-08-24 12:00:00') : null,
            revokedBy: $revoked ? new SubjectRef('user', '42') : null,
            budget: $budget,
        );
    }

    public function test_grant_created_becomes_an_approved_art14_oversight_record_with_consent_evidence(): void
    {
        $budget = new DelegationBudget(amount: 25.0, calls: 100);

        app(Dispatcher::class)->dispatch(new DelegationGrantCreated($this->grant(budget: $budget), 'Copilot'));

        $review = HumanReview::query()->firstOrFail();
        $this->assertSame('iam_delegation_grant', $review->subject_type);
        $this->assertSame('dgr_01TEST', $review->subject_id);
        $this->assertSame('approved', $review->state);
        $this->assertSame('user:42', $review->reviewer_id);
        $this->assertStringContainsString('Copilot', $review->review_notes);
        $this->assertStringContainsString('orders:read, orders:write', $review->review_notes);
        $this->assertStringContainsString('stepup_abc123', $review->review_notes);
        $this->assertStringContainsString('aal2', $review->review_notes);
        $this->assertStringContainsString('Budget', $review->review_notes);
    }

    public function test_grant_revoked_transitions_the_oversight_record_to_rejected(): void
    {
        app(Dispatcher::class)->dispatch(new DelegationGrantCreated($this->grant(), 'Copilot'));
        app(Dispatcher::class)->dispatch(new DelegationGrantRevoked($this->grant(revoked: true), 'Copilot'));

        $this->assertSame(1, HumanReview::query()->count()); // one row per grant, full story
        $review = HumanReview::query()->firstOrFail();
        $this->assertSame('rejected', $review->state);
        $this->assertStringContainsString('revoked by user:42', $review->review_notes);
    }

    public function test_revocation_without_a_prior_record_still_creates_the_evidence(): void
    {
        app(Dispatcher::class)->dispatch(new DelegationGrantRevoked($this->grant(revoked: true), 'Copilot'));

        $review = HumanReview::query()->firstOrFail();
        $this->assertSame('rejected', $review->state);
        $this->assertSame('dgr_01TEST', $review->subject_id);
    }

    public function test_agent_approval_lands_in_the_art6_risk_register_with_article_refs(): void
    {
        app(Dispatcher::class)->dispatch(new AgentApproved('agt_x', 'Copilot', 'anthropic', ['orders:read'], 'admin@example.com'));

        $entry = RiskRegisterEntry::query()->firstOrFail();
        $this->assertSame('AI agent: Copilot [agt_x]', $entry->name);
        $this->assertSame('limited', $entry->category);
        $this->assertSame('open', $entry->status);
        $this->assertSame(['AI Act Art. 6', 'AI Act Art. 14'], $entry->article_refs);
        $this->assertSame('admin@example.com', $entry->owner_id);
        $this->assertStringContainsString('anthropic', $entry->description);
        $this->assertStringContainsString('orders:read', $entry->description);
    }

    public function test_re_approval_reopens_the_existing_entry_instead_of_duplicating(): void
    {
        app(Dispatcher::class)->dispatch(new AgentApproved('agt_x', 'Copilot', null, ['orders:read'], 'admin'));
        app(Dispatcher::class)->dispatch(new AgentSuspended('agt_x', 'Copilot', 'delegation_exchange_burst', 'rebel-ai-guard'));
        app(Dispatcher::class)->dispatch(new AgentApproved('agt_x', 'Copilot', null, ['orders:read'], 'admin'));

        $this->assertSame(1, RiskRegisterEntry::query()->count());
        $this->assertSame('open', RiskRegisterEntry::query()->firstOrFail()->status);
    }

    public function test_suspend_and_retire_keep_the_register_status_honest(): void
    {
        app(Dispatcher::class)->dispatch(new AgentApproved('agt_x', 'Copilot', null, ['orders:read'], 'admin'));

        app(Dispatcher::class)->dispatch(new AgentSuspended('agt_x', 'Copilot', 'delegation_scope_probing', 'rebel-ai-guard'));
        $entry = RiskRegisterEntry::query()->firstOrFail();
        $this->assertSame('mitigating', $entry->status);
        $this->assertStringContainsString('rebel-ai-guard: delegation_scope_probing', $entry->description);

        app(Dispatcher::class)->dispatch(new AgentRetired('agt_x', 'Copilot', 'admin'));
        $this->assertSame('closed', $entry->refresh()->status);
    }

    public function test_lifecycle_events_for_agents_the_register_never_saw_are_ignored(): void
    {
        app(Dispatcher::class)->dispatch(new AgentSuspended('agt_ghost', 'Ghost', 'x', 'admin'));

        $this->assertSame(0, RiskRegisterEntry::query()->count());
    }

    public function test_configured_risk_category_is_honoured_with_a_safe_fallback(): void
    {
        config(['ai-act-compliance.iam_delegation.default_risk_category' => 'high']);
        app(Dispatcher::class)->dispatch(new AgentApproved('agt_high', 'RiskyBot', null, ['payments:read'], 'admin'));
        $this->assertSame('high', RiskRegisterEntry::query()->where('name', 'like', '%[agt_high]%')->firstOrFail()->category);

        config(['ai-act-compliance.iam_delegation.default_risk_category' => 'not-a-category']);
        app(Dispatcher::class)->dispatch(new AgentApproved('agt_typo', 'TypoBot', null, ['x:y'], 'admin'));
        $this->assertSame('limited', RiskRegisterEntry::query()->where('name', 'like', '%[agt_typo]%')->firstOrFail()->category);
    }
}
