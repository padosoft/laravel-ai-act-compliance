<?php

namespace Padosoft\AiActCompliance\MultiTenancy\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
