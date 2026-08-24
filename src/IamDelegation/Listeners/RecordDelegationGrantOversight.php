<?php

namespace Padosoft\AiActCompliance\IamDelegation\Listeners;

use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\Iam\Agents\Events\DelegationGrantCreated;

/**
 * Art. 14 (human oversight): a delegation grant IS a documented human-oversight
 * decision — a named human explicitly approved, through a parameter-bound
 * step-up consent, what an AI agent may do on their behalf. This listener turns
 * that decision into a HumanReview record so the oversight tracker shows it
 * alongside every other reviewed AI action, with the consent evidence
 * (confirmation id + achieved AAL) in the notes.
 *
 * The review lands directly in `approved`: the human decision ALREADY happened
 * (the bound consent). This is recording evidence, not asking twice.
 */
class RecordDelegationGrantOversight
{
    public function handle(DelegationGrantCreated $event): void
    {
        $grant = $event->grant;

        HumanReview::query()->create([
            'subject_type' => 'iam_delegation_grant',
            'subject_id' => $grant->id,
            'state' => HumanReviewState::APPROVED->value,
            'reviewer_id' => $grant->user->type . ':' . $grant->user->id,
            'review_notes' => implode("\n", array_filter([
                'Delegated access granted to AI agent "' . $event->agentName . '" (' . $grant->agent->id . ').',
                'Scopes: ' . implode(', ', $grant->scopes) . '.',
                'Purpose: ' . $grant->purpose . '.',
                'Expires: ' . $grant->expiresAt->format(DATE_ATOM) . '.',
                $grant->budget !== null
                    ? 'Budget: ' . json_encode($grant->budget->toArray()) . '.'
                    : null,
                $grant->consentConfirmationId !== null
                    ? 'Consent evidence: confirmation ' . $grant->consentConfirmationId
                        . ($grant->consentAal !== null ? ' (AAL ' . $grant->consentAal->value . ')' : '')
                        . '.'
                    : null,
            ])),
        ]);
    }
}
