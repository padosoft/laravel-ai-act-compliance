<?php

namespace Padosoft\AiActCompliance\Incident\Enums;

enum IncidentStatus: string
{
    case OPEN = 'open';
    case TRIAGE = 'triage';
    case MITIGATING = 'mitigating';
    case CLOSED = 'closed';
}
