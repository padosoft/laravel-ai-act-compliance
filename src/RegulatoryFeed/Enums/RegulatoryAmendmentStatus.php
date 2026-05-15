<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Enums;

enum RegulatoryAmendmentStatus: string
{
    case Pending = 'pending';
    case Triaged = 'triaged';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
}
