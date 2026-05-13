<?php

namespace Padosoft\AiActCompliance\Consent\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $table = 'consent_records';

    protected $guarded = [];

    protected $casts = [
        'granted' => 'boolean',
        'granted_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];
}
