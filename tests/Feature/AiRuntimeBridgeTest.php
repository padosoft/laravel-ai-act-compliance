<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Events\ToolApprovalResolved;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolResult;
use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\AiActCompliance\Incident\Models\IncidentTicket;
use Padosoft\AiActCompliance\Tests\TestCase;
use RuntimeException;

class AiRuntimeBridgeTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ai-act-compliance.ai_runtime.enabled', true);
    }

    private function events(): Dispatcher
    {
        return $this->app->make(Dispatcher::class);
    }

    private function agent(): Agent
    {
        return $this->createStub(Agent::class);
    }

    private function prompt(): AgentPrompt
    {
        return new AgentPrompt($this->agent(), 'hi', [], $this->createStub(TextProvider::class), 'gpt-4o-mini');
    }

    public function test_an_approval_request_opens_a_pending_oversight_record(): void
    {
        $this->events()->dispatch(new ToolApprovalRequested(
            'inv_1',
            $this->agent(),
            new Collection([new PendingApproval('call_1', 'refund_order', ['order' => 44192], 'Refunds money')]),
        ));

        $review = HumanReview::query()->sole();

        // Pending, not approved: an approval request is a question with no answer
        // yet, and recording it as approved would document a decision nobody made.
        $this->assertSame(HumanReviewState::PENDING->value, $review->state);
        $this->assertSame('ai_tool_approval', $review->subject_type);
        $this->assertSame('call_1', $review->subject_id);
        $this->assertStringContainsString('refund_order', $review->review_notes);
        $this->assertStringContainsString('Refunds money', $review->review_notes);
        $this->assertStringContainsString('44192', $review->review_notes);
    }

    public function test_tool_arguments_can_be_kept_out_of_the_record(): void
    {
        config()->set('ai-act-compliance.ai_runtime.capture_tool_arguments', false);

        $this->events()->dispatch(new ToolApprovalRequested(
            'inv_2',
            $this->agent(),
            new Collection([new PendingApproval('call_2', 'refund_order', ['iban' => 'IT60X0542811101000000123456'])]),
        ));

        $notes = HumanReview::query()->sole()->review_notes;

        $this->assertStringNotContainsString('IT60X', $notes);
        $this->assertStringContainsString('refund_order', $notes);
    }

    public function test_an_approved_call_closes_the_record_as_approved(): void
    {
        $this->events()->dispatch(new ToolApprovalRequested(
            'inv_3', $this->agent(), new Collection([new PendingApproval('call_3', 'refund_order', [])]),
        ));

        $this->events()->dispatch(new ToolApprovalResolved(
            'inv_3', $this->agent(), new Collection([new ToolResult('call_3', 'refund_order', [], 'refunded')]),
        ));

        $review = HumanReview::query()->sole();

        $this->assertSame(HumanReviewState::APPROVED->value, $review->state);
        $this->assertStringContainsString('the tool ran', $review->review_notes);
    }

    public function test_a_denied_call_closes_the_record_as_rejected(): void
    {
        $this->events()->dispatch(new ToolApprovalRequested(
            'inv_4', $this->agent(), new Collection([new PendingApproval('call_4', 'refund_order', [])]),
        ));

        $this->events()->dispatch(new ToolApprovalResolved(
            'inv_4', $this->agent(), new Collection([new ToolResult('call_4', 'refund_order', [], null, denied: true)]),
        ));

        $review = HumanReview::query()->sole();

        // "The human said no" and "the tool failed" are different facts.
        $this->assertSame(HumanReviewState::REJECTED->value, $review->state);
        $this->assertStringContainsString('did not run', $review->review_notes);
    }

    public function test_a_terminal_run_failure_opens_an_incident(): void
    {
        $this->events()->dispatch(new AgentFailed('inv_5', $this->prompt(), new RuntimeException('upstream exploded')));

        $ticket = IncidentTicket::query()->sole();

        $this->assertSame('medium', $ticket->severity);
        $this->assertStringContainsString('inv_5', $ticket->description);
        $this->assertStringContainsString(RuntimeException::class, $ticket->description);
        $this->assertSame(['art_15'], $ticket->article_refs);
    }

    public function test_a_slow_tool_failure_is_more_serious_than_an_instant_one(): void
    {
        $tool = $this->createStub(Tool::class);

        $this->events()->dispatch(new ToolFailed('inv_6', 'ti_1', $this->agent(), $tool, [], new RuntimeException('nope'), 10.0));
        $this->events()->dispatch(new ToolFailed('inv_7', 'ti_2', $this->agent(), $tool, [], new RuntimeException('timed out'), 9_000.0));

        $tickets = IncidentTicket::query()->orderBy('id')->get();

        // Ten milliseconds is a rejection; nine seconds is an upstream timeout,
        // which repeats and spreads.
        $this->assertSame('low', $tickets[0]->severity);
        $this->assertSame('medium', $tickets[1]->severity);
        $this->assertStringContainsString('Ran for 9s before failing', $tickets[1]->description);
    }

    public function test_error_messages_can_be_kept_out_of_the_ticket(): void
    {
        config()->set('ai-act-compliance.ai_runtime.capture_error_messages', false);

        $this->events()->dispatch(new AgentFailed('inv_8', $this->prompt(), new RuntimeException('quotes the prompt back')));

        $this->assertStringNotContainsString('quotes the prompt back', IncidentTicket::query()->sole()->description);
    }
}
