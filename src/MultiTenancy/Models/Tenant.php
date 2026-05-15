<?php

namespace Padosoft\AiActCompliance\MultiTenancy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Padosoft\AiActCompliance\MultiTenancy\Enums\TenantStatus;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $subscription_tier
 * @property string $status
 * @property string|null $dpo_email
 * @property string|null $contact_email
 * @property array<string,mixed>|null $config_overrides_json
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property \Illuminate\Support\Carbon|null $archived_at
 */
class Tenant extends Model
{
    protected $table = 'tenants';

    protected $guarded = ['id'];

    protected $casts = [
        'config_overrides_json' => 'array',
        'suspended_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TenantStatus::Active->value);
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active->value;
    }
}
