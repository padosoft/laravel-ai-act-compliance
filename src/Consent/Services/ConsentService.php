<?php

namespace Padosoft\AiActCompliance\Consent\Services;

use Carbon\CarbonImmutable;
use Padosoft\AiActCompliance\Consent\Models\ConsentRecord;

class ConsentService
{
    public function grant(string $userId, string $feature): ConsentRecord
    {
        return ConsentRecord::query()->updateOrCreate(
            ['user_id' => $userId, 'feature' => $feature],
            [
                'granted' => true,
                'granted_at' => CarbonImmutable::now(),
                'revoked_at' => null,
            ]
        );
    }

    public function revoke(string $userId, string $feature): ConsentRecord
    {
        return ConsentRecord::query()->updateOrCreate(
            ['user_id' => $userId, 'feature' => $feature],
            [
                'granted' => false,
                'revoked_at' => CarbonImmutable::now(),
            ]
        );
    }
}
