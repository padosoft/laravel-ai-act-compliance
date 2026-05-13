<?php

namespace Padosoft\AiActCompliance\HumanReviewTracker\Enums;

enum HumanReviewState: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ESCALATED = 'escalated';
}
