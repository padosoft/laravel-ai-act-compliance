<?php

namespace Padosoft\AiActCompliance\DSAR\Models;

use Illuminate\Database\Eloquent\Model;

class DsarRequest extends Model
{
    protected $table = 'dsar_requests';

    protected $guarded = [];

    protected $casts = [
        'sla_due_at' => 'immutable_datetime',
        'result_payload' => 'array',
    ];
}
