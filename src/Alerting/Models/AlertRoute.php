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
     *
     * Decryption failures throw. The earlier silent fallback to
     * plaintext (Copilot PR #3) was misleading: Eloquent never
     * re-encrypts an attribute on a save() that doesn't explicitly
     * set it, so a plaintext row would persist as plaintext
     * indefinitely. v1.3 is the first release that introduces the
     * column — there are no legacy v1.2.x rows in the wild, so a
     * strict policy (fail loudly on tampered ciphertext) is safe.
     */
    protected function webhookUrl(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value): ?string => $value === null || $value === ''
                ? $value
                : Crypt::decryptString($value),
            set: static fn (?string $value): ?string => $value === null || $value === ''
                ? $value
                : Crypt::encryptString($value),
        );
    }
}
