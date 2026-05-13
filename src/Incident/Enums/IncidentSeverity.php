<?php

namespace Padosoft\AiActCompliance\Incident\Enums;

enum IncidentSeverity: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
