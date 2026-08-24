<?php

namespace Padosoft\AiActCompliance\IamDelegation\Listeners;

use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\Iam\Agents\Events\AgentRetired;
use Padosoft\Iam\Agents\Events\AgentSuspended;

/**
 * Keeps the risk register honest through the agent lifecycle:
 *  - suspended (kill-switch: admin action or rebel-ai-guard anomaly)
 *    → `mitigating`, with the reason appended to the description;
 *  - retired (terminal) → `closed`.
 *
 * The entry is found by the `[agentId]` key that RegisterAgentInRiskRegister
 * put in `name`. An agent that was approved before this bridge was enabled has
 * no entry — nothing to update, and inventing a partial one here would record
 * a lifecycle tail without its head (the approval evidence).
 */
class UpdateAgentRiskStatus
{
    public function handle(AgentSuspended|AgentRetired $event): void
    {
        $entry = RiskRegisterEntry::query()
            ->where('name', 'like', '%[' . $event->agentId . ']%')
            ->first();
        if ($entry === null) {
            return;
        }

        if ($event instanceof AgentSuspended) {
            $entry->update([
                'status' => 'mitigating',
                'description' => trim(($entry->description ?? '')
                    . "\nSuspended by " . $event->actor . ': ' . $event->reason . '.'),
            ]);

            return;
        }

        $entry->update([
            'status' => 'closed',
            'description' => trim(($entry->description ?? '')
                . "\nRetired by " . $event->actor . ' (terminal).'),
        ]);
    }
}
