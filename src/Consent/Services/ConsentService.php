<?php

namespace Padosoft\AiActCompliance\Consent\Services;

use Padosoft\AiActCompliance\Consent\Models\ConsentRecord;

class ConsentService
{
    public function grant(string $userId, string $feature): ConsentRecord
    {
        return ConsentRecord::query()->updateOrCreate(
            ['user_id' => $userId, 'feature' => $feature],
            ['granted' => true]
        );
    }
}
