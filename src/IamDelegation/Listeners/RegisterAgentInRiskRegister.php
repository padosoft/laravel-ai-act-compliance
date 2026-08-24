<?php

namespace Padosoft\AiActCompliance\IamDelegation\Listeners;

use Padosoft\AiActCompliance\RiskRegister\Enums\AiActRiskCategory;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\AiActCompliance\RiskRegister\Services\RiskRegisterService;
use Padosoft\Iam\Agents\Events\AgentApproved;

/**
 * Art. 6 (risk classification): an AI agent that can act on users' behalf is an
 * AI system in production and belongs in the risk register from the moment a
 * human activates it. The entry cites Art. 6 (classification) and Art. 14 (the
 * human-oversight machinery every grant goes through).
 *
 * The agent id rides in square brackets inside `name` — the stable key the
 * lifecycle listener uses to find this entry on suspend/retire. The risk
 * category is configurable (`iam_delegation.default_risk_category`, default
 * `limited`): whether a delegated agent is high-risk depends on the DOMAIN it
 * acts in, which only the host application knows.
 */
class RegisterAgentInRiskRegister
{
    public function __construct(private readonly RiskRegisterService $risks)
    {
    }

    public function handle(AgentApproved $event): void
    {
        // One entry per agent: a re-approval (suspend → resume → re-approve
        // flows) must not fill the register with duplicates.
        $existing = RiskRegisterEntry::query()
            ->where('name', 'like', '%[' . $event->agentId . ']%')
            ->first();
        if ($existing !== null) {
            $existing->update(['status' => 'open']);

            return;
        }

        $category = config('ai-act-compliance.iam_delegation.default_risk_category', AiActRiskCategory::LIMITED->value);

        $this->risks->create([
            'name' => 'AI agent: ' . $event->name . ' [' . $event->agentId . ']',
            'category' => AiActRiskCategory::tryFrom(is_string($category) ? $category : '')?->value
                ?? AiActRiskCategory::LIMITED->value,
            'status' => 'open',
            'description' => implode("\n", array_filter([
                'Delegated-access AI agent approved for production by ' . $event->actor . '.',
                $event->operator !== null ? 'Operator: ' . $event->operator . '.' : null,
                'Scopes ceiling (max_scopes): ' . implode(', ', $event->maxScopes) . '.',
                'Every delegation to this agent is individually consented and tracked as a human-oversight item.',
            ])),
            'owner_id' => $event->actor,
            'article_refs' => ['AI Act Art. 6', 'AI Act Art. 14'],
        ]);
    }
}
