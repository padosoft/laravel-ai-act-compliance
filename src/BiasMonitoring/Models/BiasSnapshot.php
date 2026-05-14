<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Models;

use Illuminate\Database\Eloquent\Model;

class BiasSnapshot extends Model
{
    protected $table = 'bias_snapshots';

    protected $guarded = [];

    protected $casts = [
        'score' => 'float',
        'delta' => 'float',
        'payload' => 'array',
        // v1.2 additive columns
        'article_evidence_json' => 'array',
        'disparity_score' => 'float',
    ];
}
