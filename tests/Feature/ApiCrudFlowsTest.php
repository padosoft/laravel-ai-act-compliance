<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use Padosoft\AiActCompliance\Tests\TestCase;

class ApiCrudFlowsTest extends TestCase
{
    public function test_risk_and_incident_and_consent_and_dsar_flows(): void
    {
        $risk = $this->postJson('/api/admin/ai-act-compliance/risks', [
            'name' => 'Model hallucination',
            'category' => 'high',
        ])->assertCreated()->json('data');

        $this->patchJson('/api/admin/ai-act-compliance/risks/' . $risk['id'], [
            'status' => 'mitigating',
        ])->assertOk()->assertJsonPath('data.status', 'mitigating');

        $incident = $this->postJson('/api/admin/ai-act-compliance/incidents', [
            'title' => 'Unsafe output',
            'severity' => 'critical',
        ])->assertCreated()->json('data');

        $this->postJson('/api/admin/ai-act-compliance/incidents/' . $incident['id'] . '/transition', [
            'to_status' => 'triage',
        ])->assertOk()->assertJsonPath('data.status', 'triage');

        $this->postJson('/api/admin/ai-act-compliance/consent/grant', [
            'user_id' => 'u-1',
            'feature' => 'ai-chat',
        ])->assertOk()->assertJsonPath('data.granted', true);

        $dsar = $this->postJson('/api/admin/ai-act-compliance/dsar', [
            'user_id' => 'u-1',
            'type' => 'rectify',
        ])->assertCreated()->json('data');

        $this->postJson('/api/admin/ai-act-compliance/dsar/' . $dsar['id'] . '/execute')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }
}
