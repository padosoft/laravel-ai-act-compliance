<?php

namespace Padosoft\AiActCompliance\Alerting\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AlertRoute extends Model
{
    protected $table = 'alert_routes';

    protected $guarded = [];

    protected $casts = [
        'severity_filter_json' => 'array',
        'enabled' => 'boolean',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'tripped_until' => 'datetime',
        'consecutive_failures' => 'integer',
    ];

    /**
     * Encrypt webhook URLs at rest. The migration column stays a
     * regular `text` (no DB-level encryption needed because we
     * round-trip ciphertext through Crypt::encryptString), so a DB
     * dump or accidental leak does not expose the webhook secret in
     * plaintext.
     */
    protected function webhookUrl(): Attribute
    {
        return Attribute::make(
            get: static function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    // Legacy plaintext rows during the v1.2.x → v1.3
                    // migration window: surface as-is so the route is
                    // still usable; a subsequent save() will round-
                    // trip it through the encrypt accessor.
                    return $value;
                }
            },
            set: static fn (?string $value): ?string => $value === null || $value === ''
                ? $value
                : Crypt::encryptString($value),
        );
    }
}
