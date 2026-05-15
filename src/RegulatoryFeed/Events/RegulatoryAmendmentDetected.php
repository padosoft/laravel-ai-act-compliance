<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Events;

use Padosoft\AiActCompliance\RegulatoryFeed\Models\RegulatoryAmendment;

class RegulatoryAmendmentDetected
{
    public function __construct(
        public readonly RegulatoryAmendment $amendment,
        public readonly bool $isNew,
    ) {}
}
