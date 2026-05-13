<?php

namespace Padosoft\AiActCompliance\DSAR\Enums;

enum DsarType: string
{
    case EXPORT = 'export';
    case DELETE = 'delete';
    case RECTIFY = 'rectify';
}
