<?php

namespace Padosoft\AiActCompliance\HumanReviewTracker\Services;

use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;

class HumanReviewService
{
    public function transition(HumanReview $review, HumanReviewState $state): HumanReview
    {
        $review->update(['state' => $state->value]);

        return $review->refresh();
    }
}
