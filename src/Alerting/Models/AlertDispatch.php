<?php

namespace Padosoft\AiActCompliance\Alerting\Models;

use Illuminate\Database\Eloquent\Model;

class AlertDispatch extends Model
{
    protected $table = 'alert_dispatches';

    protected $guarded = [];

    protected $casts = [
        'payload_json' => 'array',
        'ok' => 'boolean',
        'transient_failure' => 'boolean',
        'http_status' => 'integer',
    ];
}
