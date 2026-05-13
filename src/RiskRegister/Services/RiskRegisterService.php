<?php

namespace Padosoft\AiActCompliance\RiskRegister\Services;

use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;

class RiskRegisterService
{
    public function create(array $data): RiskRegisterEntry
    {
        return RiskRegisterEntry::query()->create($data);
    }
}
