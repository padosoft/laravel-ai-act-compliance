<?php

namespace Padosoft\AiActCompliance\Routines\Listeners;

use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\Routines\Events\RoutineResolved;

/**
 * Closes the oversight record {@see RecordRoutinePauseOversight} opened.
 *
 * A pending review that is never closed is worse than no review: the tracker
 * shows a decision permanently outstanding for one that was taken minutes
 * later, and every "outstanding oversight" count is wrong from then on.
 *
 * It listens to `RoutineResolved` and not to the run finishing, because those
 * are different facts: on approval the work resumes and ends later — sometimes
 * much later, sometimes failing — and «the human said yes» must not be recorded
 * as «the work succeeded».
 */
class ResolveRoutineOversight
{
    public function handle(RoutineResolved $event): void
    {
        $review = HumanReview::query()
            ->where('subject_type', 'routine_run')
            ->where('subject_id', $event->run->id)
            ->first();

        if ($review === null) {
            return;
        }

        $review->update([
            'state' => $event->approved ? HumanReviewState::APPROVED->value : HumanReviewState::REJECTED->value,
            'reviewer_id' => $event->resolvedBy,
            'review_notes' => trim(($review->review_notes ?? '')."\n"
                .($event->approved
                    ? 'Approved by '.$event->resolvedBy.'; the run resumed from where it stopped.'
                    : 'Rejected by '.$event->resolvedBy.'; the action was not performed.')
                .($event->note !== '' ? ' Reason: '.$event->note : '')),
        ]);
    }
}
