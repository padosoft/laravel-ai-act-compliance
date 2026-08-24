<?php

namespace Padosoft\AiActCompliance\IamDelegation\Listeners;

use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\AiActCompliance\HumanReviewTracker\Services\HumanReviewService;
use Padosoft\Iam\Agents\Events\DelegationGrantRevoked;

/**
 * Art. 14, the other half: revoking a delegation is ALSO a human-oversight
 * decision and must be as visible as the grant. The oversight record of the
 * grant transitions to `rejected` (the human withdrew the authority), keeping
 * one row per grant with its full story in the notes.
 *
 * A revocation for a grant this tracker never saw (created before the bridge
 * was enabled) still creates the record — late evidence beats no evidence.
 */
class RecordDelegationGrantRevocation
{
    public function __construct(private readonly HumanReviewService $reviews)
    {
    }

    public function handle(DelegationGrantRevoked $event): void
    {
        $grant = $event->grant;

        $revokedBy = $grant->revokedBy !== null
            ? $grant->revokedBy->type . ':' . $grant->revokedBy->id
            : 'unknown';
        $note = 'Delegation revoked by ' . $revokedBy
            . ($grant->revokedAt !== null ? ' at ' . $grant->revokedAt->format(DATE_ATOM) : '')
            . '.';

        $review = HumanReview::query()
            ->where('subject_type', 'iam_delegation_grant')
            ->where('subject_id', $grant->id)
            ->first();

        if ($review === null) {
            HumanReview::query()->create([
                'subject_type' => 'iam_delegation_grant',
                'subject_id' => $grant->id,
                'state' => HumanReviewState::REJECTED->value,
                'reviewer_id' => $revokedBy,
                'review_notes' => 'Delegated access to AI agent "' . $event->agentName . '" (' . $grant->agent->id . ").\n" . $note,
            ]);

            return;
        }

        $review->update([
            'review_notes' => trim(($review->review_notes ?? '') . "\n" . $note),
        ]);
        $this->reviews->transition($review, HumanReviewState::REJECTED);
    }
}
