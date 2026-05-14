<?php

namespace Padosoft\AiActCompliance\FRIA\Models;

use Illuminate\Database\Eloquent\Model;

class FriaAssessment extends Model
{
    protected $table = 'fria_assessments';

    protected $guarded = [];

    protected $casts = [
        'risks_json' => 'array',
        'mitigations_json' => 'array',
        'next_review_at' => 'datetime',
        'signed_off_at' => 'datetime',
    ];
}
