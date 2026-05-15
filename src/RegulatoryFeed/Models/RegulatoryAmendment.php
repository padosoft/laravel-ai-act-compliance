<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string $source_driver
 * @property string $external_id
 * @property string $source_url
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 * @property array<int,string>|null $impacted_clauses_json
 * @property string $status
 * @property string $severity
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon $ingested_at
 * @property \Illuminate\Support\Carbon|null $triaged_at
 * @property string|null $triaged_by
 * @property string|null $triage_notes
 */
class RegulatoryAmendment extends Model
{
    protected $table = 'regulatory_amendments';

    protected $guarded = ['id'];

    protected $casts = [
        'impacted_clauses_json' => 'array',
        'published_at' => 'datetime',
        'ingested_at' => 'datetime',
        'triaged_at' => 'datetime',
    ];
}
