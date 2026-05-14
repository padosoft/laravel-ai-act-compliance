<?php

namespace Padosoft\AiActCompliance\DSAR\Enums;

enum DsarStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
}
