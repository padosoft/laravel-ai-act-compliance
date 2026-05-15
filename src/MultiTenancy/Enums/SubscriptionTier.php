<?php

namespace Padosoft\AiActCompliance\MultiTenancy\Enums;

enum SubscriptionTier: string
{
    case Free = 'free';
    case Team = 'team';
    case Enterprise = 'enterprise';
}
