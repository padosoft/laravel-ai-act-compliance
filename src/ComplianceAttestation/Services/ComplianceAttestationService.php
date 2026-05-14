<?php

namespace Padosoft\AiActCompliance\ComplianceAttestation\Services;

use Padosoft\AiActCompliance\ComplianceAttestation\Models\ComplianceAttestation;

class ComplianceAttestationService
{
    public function create(array $data): ComplianceAttestation
    {
        return ComplianceAttestation::query()->create($data);
    }
}
