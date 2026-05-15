<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Enums;

enum RegulatoryAmendmentSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
