<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Events;

use Illuminate\Queue\SerializesModels;
use Padosoft\AiActCompliance\RegulatoryFeed\Models\RegulatoryAmendment;

class RegulatoryAmendmentDetected
{
    // Without SerializesModels, a queued listener would serialize the
    // full Eloquent model (incl. potentially large `body` text); the
    // trait makes Laravel persist just the model identifier and
    // re-fetch on dequeue. Copilot iter-1 review on PR #4.
    use SerializesModels;

    public function __construct(
        public readonly RegulatoryAmendment $amendment,
        public readonly bool $isNew,
    ) {}
}
