<?php

namespace Padosoft\AiActCompliance\FRIA\Enums;

/**
 * FRIA — Fundamental Rights Impact Assessment (AI Act Art. 27).
 *
 * Lifecycle:
 *  - DRAFT       initial entry; not yet scheduled for review.
 *  - ACTIVE      scheduled with a next_review_at; the working state.
 *  - REVIEW_DUE  derived state for assessments whose next_review_at
 *                has passed without sign-off.
 *  - RETIRED     superseded or no longer applicable; preserved for
 *                audit but excluded from due-review reporting.
 */
enum FriaStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case REVIEW_DUE = 'review_due';
    case RETIRED = 'retired';
}
