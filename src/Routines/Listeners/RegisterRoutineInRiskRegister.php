<?php

namespace Padosoft\AiActCompliance\Routines\Listeners;

use Padosoft\AiActCompliance\RiskRegister\Enums\AiActRiskCategory;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\AiActCompliance\RiskRegister\Services\RiskRegisterService;
use Padosoft\Routines\Events\RoutineMandateGranted;

/**
 * Art. 6 (risk classification): a routine authorised to act unattended is an AI
 * system in production, and belongs in the register from the moment a human
 * grants it that authority — not from the moment it first fires. The two are
 * different days, and the register should show the earlier one.
 *
 * The routine id rides in square brackets inside `name`: the stable key the
 * lifecycle listener uses to find this entry on suspension. Re-granting a
 * mandate (payload changed, consent renewed) reopens the same entry instead of
 * adding a second — a routine is one system however many times its consent is
 * refreshed.
 *
 * The risk category is configurable (`routines.default_risk_category`, default
 * `limited`): whether an unattended routine is high-risk depends on the DOMAIN
 * it acts in, which only the host application knows.
 */
class RegisterRoutineInRiskRegister
{
    public function __construct(private readonly RiskRegisterService $risks)
    {
    }

    public function handle(RoutineMandateGranted $event): void
    {
        $routine = $event->routine;

        $existing = RiskRegisterEntry::query()
            ->where('name', 'like', '%['.$routine->id.']%')
            ->first();
        if ($existing !== null) {
            $existing->update(['status' => 'open']);

            return;
        }

        $category = config('ai-act-compliance.routines.default_risk_category', AiActRiskCategory::LIMITED->value);

        $this->risks->create([
            'name' => 'Unattended routine: '.$routine->name.' ['.$routine->id.']',
            'category' => AiActRiskCategory::tryFrom(is_string($category) ? $category : '')?->value
                ?? AiActRiskCategory::LIMITED->value,
            'status' => 'open',
            'description' => implode("\n", array_filter([
                'Routine authorised to act unattended, on behalf of '.$routine->owner.'.',
                'Trigger: '.$routine->trigger_kind.($routine->cron !== null ? ' ('.$routine->cron.', '.$routine->timezone.')' : '').'.',
                'Target: '.$routine->target_type.'.',
                'Action classes: '.implode(', ', $event->mandate->actionClasses).'.',
                'Anything outside the mandate pauses the run and asks the owner; it is never performed silently.',
            ])),
            'owner_id' => $routine->owner,
            'article_refs' => ['AI Act Art. 6', 'AI Act Art. 14'],
        ]);
    }
}
