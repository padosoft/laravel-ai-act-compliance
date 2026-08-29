<?php

namespace Padosoft\AiActCompliance\Routines\Listeners;

use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\Routines\Events\RoutineMandateGranted;

/**
 * Art. 14 (human oversight): granting a routine its standing mandate IS a
 * documented human-oversight decision, and the most consequential one in the
 * system — a named human decided what an automation may do **on its own, when
 * nobody is watching**. Interactive consent is a person approving one action;
 * this is a person approving a class of actions in advance, which is precisely
 * why it has to be recorded rather than assumed.
 *
 * The review lands directly in `approved`: the human decision ALREADY happened,
 * bound to the digest of the approved payload. This records evidence, it does
 * not ask twice.
 */
class RecordMandateOversight
{
    public function handle(RoutineMandateGranted $event): void
    {
        $routine = $event->routine;
        $mandate = $event->mandate;

        HumanReview::query()->create([
            'subject_type' => 'routine_mandate',
            'subject_id' => $routine->id,
            'state' => HumanReviewState::APPROVED->value,
            'reviewer_id' => $routine->owner,
            'review_notes' => implode("\n", array_filter([
                'Standing mandate granted to routine "'.$routine->name.'" ('.$routine->id.').',
                'Action classes: '.($mandate->actionClasses === []
                    ? 'none — the mandate authorises nothing (fail-closed).'
                    : implode(', ', $mandate->actionClasses).'.'),
                'Target: '.$mandate->targetType.', payload digest '.$mandate->payloadDigest.'.',
                $mandate->budgetCeiling !== null
                    ? 'Budget ceiling: '.$mandate->budgetCeiling.' '.$mandate->currency.'.'
                    : null,
                $mandate->notAfter !== null
                    ? 'Not after: '.$mandate->notAfter->format(DATE_ATOM).'.'
                    : null,
                $event->confirmationId !== null
                    ? 'Consent evidence: confirmation '.$event->confirmationId
                        .($event->aal !== null ? ' (AAL '.$event->aal.')' : '').'.'
                    : null,
                // Il digest e' cio' che rende il consenso verificabile: se il payload cambia, il
                // mandato smette di coprirlo e serve un consenso nuovo. Scriverlo qui e' cio' che
                // permette a un revisore di ricostruire A COSA la persona aveva detto di si'.
            ])),
        ]);
    }
}
