<?php

namespace Padosoft\\AiActCompliance\\Incident\\Models;

use Illuminate\\Database\\Eloquent\\Model;

class IncidentStateTransition extends Model
{
    protected $table = 'incident_state_transitions';

    protected $guarded = [];
}
