<?php

namespace Padosoft\AiActCompliance\Incident\Services;

use Padosoft\AiActCompliance\Incident\Enums\IncidentStatus;
use Padosoft\AiActCompliance\Incident\Models\IncidentStateTransition;
use Padosoft\AiActCompliance\Incident\Models\IncidentTicket;

class IncidentService
{
    public function open(array $data): IncidentTicket
    {
        $ticket = IncidentTicket::query()->create($data + ['status' => IncidentStatus::OPEN->value]);

        IncidentStateTransition::query()->create([
            'incident_ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => IncidentStatus::OPEN->value,
        ]);

        return $ticket;
    }
}
