<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Padosoft\AiActCompliance\BiasMonitoring\Models\BiasSnapshot;
use Padosoft\AiActCompliance\ComplianceAttestation\Models\ComplianceAttestation;
use Padosoft\AiActCompliance\Consent\Models\ConsentRecord;
use Padosoft\AiActCompliance\DSAR\Models\DsarRequest;
use Padosoft\AiActCompliance\Incident\Models\IncidentTicket;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;

class ComplianceOverviewController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'ai-act-compliance',
            'kpi' => [
                'dsar_open' => DsarRequest::query()->whereIn('status', ['pending', 'in_progress'])->count(),
                'incidents_open' => IncidentTicket::query()->where('status', '!=', 'closed')->count(),
                'consent_granted' => ConsentRecord::query()->where('granted', true)->count(),
                'risks_open' => RiskRegisterEntry::query()->where('status', '!=', 'closed')->count(),
                'bias_snapshots' => BiasSnapshot::query()->count(),
                'attestations' => ComplianceAttestation::query()->count(),
            ],
        ]);
    }
}
