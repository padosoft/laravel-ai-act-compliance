<?php

namespace Padosoft\AiActCompliance\Routines\Listeners;

use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\Routines\Events\RoutinePaused;

/**
 * Art. 14, the live half: the automation met something its mandate does not
 * cover and **stopped to ask**. That is human oversight being exercised, and
 * the review opens `pending` because at this instant the decision genuinely is
 * outstanding.
 *
 * It is also the one oversight item that can rot: a pending review nobody
 * answers is a routine frozen forever, and the tracker's "outstanding
 * oversight" count is exactly where that becomes visible. (rebel-ai-guard
 * watches the same condition from the other side, as
 * `routine_approval_starvation`.)
 *
 * Keyed by RUN id, not routine id: the same routine pauses many times, and each
 * pause is its own question with its own answer.
 */
class RecordRoutinePauseOversight
{
    public function handle(RoutinePaused $event): void
    {
        $run = $event->run;

        HumanReview::query()->create([
            'subject_type' => 'routine_run',
            'subject_id' => $run->id,
            'state' => HumanReviewState::PENDING->value,
            'reviewer_id' => $event->routine->owner,
            'review_notes' => implode("\n", array_filter([
                'Routine "'.$event->routine->name.'" ('.$event->routine->id.') paused, awaiting a human.',
                $run->action_class !== null ? 'Action class outside the mandate: '.$run->action_class.'.' : null,
                $run->question !== null && $run->question !== '' ? 'Question: '.$run->question : null,
                $event->result->message !== '' ? 'Detail: '.$event->result->message : null,
            ])),
        ]);
    }
}
