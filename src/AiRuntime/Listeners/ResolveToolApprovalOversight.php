<?php

namespace Padosoft\AiActCompliance\AiRuntime\Listeners;

use Laravel\Ai\Events\ToolApprovalResolved;
use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;

/**
 * Closes the oversight record {@see RecordToolApprovalOversight} opened.
 *
 * A pending review that is never closed is worse than no review: the tracker
 * shows a decision permanently outstanding for an action that was decided
 * minutes later, and every "outstanding oversight" count is wrong from then on.
 */
class ResolveToolApprovalOversight
{
    public function handle(ToolApprovalResolved $event): void
    {
        foreach ($event->toolResults as $result) {
            $review = HumanReview::query()
                ->where('subject_type', 'ai_tool_approval')
                // ToolResult::$id is the tool call id, which is the same id the
                // PendingApproval carried — that is what makes the pair joinable.
                ->where('subject_id', $result->id)
                ->first();

            if ($review === null) {
                continue;
            }

            // A denied call never ran, and the record has to say which of the two
            // happened: "the human said no" and "the tool failed" are different
            // facts about the same action.
            $review->update([
                'state' => $result->denied ? HumanReviewState::REJECTED->value : HumanReviewState::APPROVED->value,
                'review_notes' => trim(($review->review_notes ?? '')."\n"
                    .($result->denied ? 'Denied by the user; the tool did not run.' : 'Approved by the user; the tool ran.')
                    .' Resolved on run '.$event->invocationId.'.'),
            ]);
        }
    }
}
