<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\AiActCompliance\Tests\TestCase;
use Padosoft\Iam\Agents\Events\AgentApproved;
use Padosoft\Iam\Agents\Events\DelegationGrantCreated;
use Padosoft\Iam\Contracts\Delegation\DelegationGrant;
use Padosoft\Iam\Contracts\Delegation\DelegationGrantStatus;
use Padosoft\Iam\Contracts\Support\SubjectRef;

/**
 * The bridge is OPT-IN: with the default config (`iam_delegation.enabled` false)
 * the listeners are never registered, so iam-agents events flow through the
 * dispatcher without touching the compliance tables.
 */
class IamDelegationBridgeDisabledTest extends TestCase
{
    public function test_default_config_registers_no_listeners(): void
    {
        app(Dispatcher::class)->dispatch(new DelegationGrantCreated(new DelegationGrant(
            id: 'dgr_off',
            user: new SubjectRef('user', '1'),
            agent: new SubjectRef('agent', 'agt_off'),
            scopes: ['x:y'],
            purpose: 'p',
            status: DelegationGrantStatus::Active,
            expiresAt: new \DateTimeImmutable('+1 day'),
            createdAt: new \DateTimeImmutable('-1 hour'),
        ), 'OffBot'));
        app(Dispatcher::class)->dispatch(new AgentApproved('agt_off', 'OffBot', null, ['x:y'], 'admin'));

        $this->assertSame(0, HumanReview::query()->count());
        $this->assertSame(0, RiskRegisterEntry::query()->count());
    }
}
