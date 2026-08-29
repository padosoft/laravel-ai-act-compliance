<?php

namespace Padosoft\AiActCompliance\Routines\Listeners;

use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\Routines\Events\RoutineSuspended;

/**
 * Keeps the register honest when a routine is stopped — by an admin, by an
 * exhausted budget, by a vanished target, or by a rebel-ai-guard anomaly. The
 * status becomes `mitigating` and the reason is appended, so the register shows
 * WHY an autonomous system was taken out of service.
 *
 * The entry is found by the `[routineId]` key that
 * {@see RegisterRoutineInRiskRegister} put in `name`. A routine that never had
 * a mandate has no entry — nothing to update, and inventing a partial one here
 * would record a lifecycle tail without its head (the consent evidence).
 */
class UpdateRoutineRiskStatus
{
    public function handle(RoutineSuspended $event): void
    {
        $entry = RiskRegisterEntry::query()
            ->where('name', 'like', '%['.$event->routine->id.']%')
            ->first();
        if ($entry === null) {
            return;
        }

        $entry->update([
            'status' => 'mitigating',
            'description' => trim(($entry->description ?? '')
                ."\nSuspended: ".$event->reason.($event->detail !== '' ? ' — '.$event->detail : '').'.'),
        ]);
    }
}
