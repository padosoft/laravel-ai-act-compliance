<?php

namespace Padosoft\AiActCompliance\RiskRegister\Enums;

enum AiActRiskCategory: string
{
    case LOW = 'low';
    case LIMITED = 'limited';
    case HIGH = 'high';
    case UNACCEPTABLE = 'unacceptable';
}
