<?php

namespace Padosoft\AiActCompliance\FRIA\Services;

use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\FRIA\Enums\FriaStatus;
use Padosoft\AiActCompliance\FRIA\Models\FriaAssessment;

/**
 * FriaService — AI Act Art. 27 (Fundamental Rights Impact Assessment).
 *
 * Workflow:
 *   open()              create a draft assessment; defaults applied.
 *   updateMitigations() update mitigations_json without touching risks.
 *   scheduleReview()    set next_review_at + flip to ACTIVE.
 *   signOff()           record signer + timestamp; stays ACTIVE.
 *   retire()            flip to RETIRED; preserved for audit.
 */
class FriaService
{
    public function open(array $data): FriaAssessment
    {
        $defaults = [
            'review_cadence_days' => (int) config('ai-act-compliance.fria.default_review_cadence_days', 180),
            'status' => FriaStatus::DRAFT->value,
        ];

        return FriaAssessment::query()->create(array_merge($defaults, $data));
    }

    public function updateMitigations(FriaAssessment $assessment, array $mitigations): FriaAssessment
    {
        $assessment->forceFill(['mitigations_json' => $mitigations])->save();

        return $assessment->refresh();
    }

    public function scheduleReview(FriaAssessment $assessment, ?int $days = null): FriaAssessment
    {
        $cadence = $days ?? (int) ($assessment->review_cadence_days
            ?: config('ai-act-compliance.fria.default_review_cadence_days', 180));

        $assessment->forceFill([
            'review_cadence_days' => $cadence,
            'next_review_at' => Carbon::now()->addDays($cadence),
            'status' => FriaStatus::ACTIVE->value,
        ])->save();

        return $assessment->refresh();
    }

    public function signOff(FriaAssessment $assessment, string $signer): FriaAssessment
    {
        $assessment->forceFill([
            'signed_off_by' => $signer,
            'signed_off_at' => Carbon::now(),
            'status' => FriaStatus::ACTIVE->value,
        ])->save();

        return $assessment->refresh();
    }

    public function retire(FriaAssessment $assessment): FriaAssessment
    {
        $assessment->forceFill(['status' => FriaStatus::RETIRED->value])->save();

        return $assessment->refresh();
    }

    public function isReviewDue(FriaAssessment $assessment, ?Carbon $now = null): bool
    {
        if ($assessment->next_review_at === null) {
            return false;
        }

        $now ??= Carbon::now();

        return $assessment->next_review_at->lessThan($now);
    }
}
