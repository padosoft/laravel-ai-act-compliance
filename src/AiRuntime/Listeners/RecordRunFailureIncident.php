<?php

namespace Padosoft\AiActCompliance\AiRuntime\Listeners;

use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\ToolFailed;
use Padosoft\AiActCompliance\Incident\Enums\IncidentSeverity;
use Padosoft\AiActCompliance\Incident\Services\IncidentService;

/**
 * Opens an incident when an AI run, or one of its tools, fails.
 *
 * Art. 15 wants serious malfunctions recorded, and until `laravel/ai` 0.11 the
 * only signal an application had was an exception somewhere in a log. These
 * events carry what a ticket actually needs: which agent, which run, which tool,
 * the exception class, and **how long it ran before failing** — the number that
 * separates a timeout from a rejection, and therefore an infrastructure incident
 * from a logic one.
 *
 * `AgentFailed` fires only when the failure is terminal — a failover that still
 * has a provider left to try does not reach it — so a transient blip that the
 * SDK recovered from does not open a ticket.
 */
class RecordRunFailureIncident
{
    public function __construct(private readonly IncidentService $incidents) {}

    public function handleAgentFailed(AgentFailed $event): void
    {
        $this->incidents->open([
            'title' => 'AI run failed: '.class_basename($event->prompt->agent::class),
            'severity' => IncidentSeverity::MEDIUM->value,
            'description' => implode("\n", [
                'The run terminated without producing a response.',
                'Run: '.$event->invocationId.'.',
                'Model: '.$event->prompt->model.'.',
                'Exception: '.$event->exception::class.'.',
                $this->message($event->exception->getMessage()),
            ]),
            'article_refs' => ['art_15'],
        ]);
    }

    public function handleToolFailed(ToolFailed $event): void
    {
        $seconds = round($event->time / 1000, 1);

        $this->incidents->open([
            'title' => 'AI tool failed: '.$event->tool::class,
            // A tool that ran long before throwing looks like an upstream
            // timeout, which is the kind of failure that repeats and spreads.
            'severity' => ($event->time >= $this->slowThreshold()
                ? IncidentSeverity::MEDIUM
                : IncidentSeverity::LOW)->value,
            'description' => implode("\n", [
                'A tool invoked by agent "'.$event->agent::class.'" threw.',
                'Run: '.$event->invocationId.', tool invocation: '.$event->toolInvocationId.'.',
                'Ran for '.$seconds.'s before failing.',
                'Exception: '.$event->exception::class.'.',
                $this->message($event->exception->getMessage()),
            ]),
            'article_refs' => ['art_15'],
        ]);
    }

    /**
     * Exception messages are provider text and can quote the prompt back, so
     * capture is opt-out and what is stored is bounded.
     */
    private function message(string $message): string
    {
        if (config('ai-act-compliance.ai_runtime.capture_error_messages', true) !== true) {
            return 'Message: (capture disabled).';
        }

        $limit = max(0, (int) config('ai-act-compliance.ai_runtime.error_message_limit', 500));

        return $limit === 0
            ? 'Message: (capture disabled).'
            : 'Message: '.mb_substr($message, 0, $limit);
    }

    private function slowThreshold(): float
    {
        return (float) config('ai-act-compliance.ai_runtime.slow_tool_ms', 5000);
    }
}
