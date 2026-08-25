<?php

namespace Padosoft\AiActCompliance\AiRuntime\Listeners;

use Laravel\Ai\Events\ToolApprovalRequested;
use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;

/**
 * Art. 14 (human oversight), at the level of a single action.
 *
 * A delegation grant records that a human approved what an agent *may* do. This
 * records that a human was asked about what the agent is *about to* do — the
 * per-action confirmation, which is the evidence an auditor asks for when the
 * action had an effect: money moved, a record changed, a message went out.
 *
 * The review is created `pending` on purpose. Unlike a grant, whose consent has
 * already happened by the time the event fires, an approval request is a
 * question with no answer yet; recording it as approved would document a
 * decision nobody has made. {@see ResolveToolApprovalOversight} closes it.
 */
class RecordToolApprovalOversight
{
    public function handle(ToolApprovalRequested $event): void
    {
        $reviewer = $this->reviewer($event);

        foreach ($event->pendingApprovals as $approval) {
            HumanReview::query()->updateOrCreate(
                [
                    'subject_type' => 'ai_tool_approval',
                    'subject_id' => $approval->id,
                ],
                [
                    'state' => HumanReviewState::PENDING->value,
                    'reviewer_id' => $reviewer,
                    'review_notes' => implode("\n", array_filter([
                        'Agent "' . $event->agent::class . '" asked to run tool "' . $approval->tool . '".',
                        'Run: ' . $event->invocationId . '.',
                        $event->conversationId !== null ? 'Conversation: ' . $event->conversationId . '.' : null,
                        $approval->reason !== null ? 'Reason given: ' . $approval->reason . '.' : null,
                        $this->arguments($approval->arguments),
                    ])),
                ],
            );
        }
    }

    /**
     * Tool arguments are the *what* of the action, and they are also the most
     * likely place for personal data to appear in this record — an address, an
     * order, an amount. Capture is opt-out and the stored value is bounded.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function arguments(array $arguments): ?string
    {
        if ($arguments === [] || config('ai-act-compliance.ai_runtime.capture_tool_arguments', true) !== true) {
            return null;
        }

        $limit = max(0, (int) config('ai-act-compliance.ai_runtime.tool_argument_limit', 500));

        if ($limit === 0) {
            return null;
        }

        $encoded = json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : 'Arguments: ' . mb_substr($encoded, 0, $limit);
    }

    private function reviewer(ToolApprovalRequested $event): ?string
    {
        $user = $event->conversationUser;

        if ($user === null) {
            return null;
        }

        // The conversation user is whatever the host app bound; an Eloquent model
        // exposes a key, anything else is only useful if it can name itself.
        return match (true) {
            isset($user->id) => (string) $user->id,
            $user instanceof \Stringable => (string) $user,
            default => null,
        };
    }
}
