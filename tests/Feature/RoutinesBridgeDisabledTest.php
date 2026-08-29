<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\AiActCompliance\Tests\TestCase;
use Padosoft\Routines\Contracts\Consent\RoutineMandate;
use Padosoft\Routines\Events\RoutineMandateGranted;
use Padosoft\Routines\Models\Routine;

/**
 * The bridge is OPT-IN: with the default config (`routines.enabled` false) the listeners are
 * never registered, so laravel-routines events flow through the dispatcher without touching
 * the compliance tables. It writes records, and an application should say yes before another
 * package starts writing.
 */
class RoutinesBridgeDisabledTest extends TestCase
{
    public function test_default_config_registers_no_listeners(): void
    {
        $routine = new Routine;
        $routine->forceFill(['id' => 'rt_off', 'owner' => 'user:1', 'name' => 'Off', 'target_type' => 't']);

        app(Dispatcher::class)->dispatch(new RoutineMandateGranted($routine, new RoutineMandate(
            targetType: 't',
            payloadDigest: 'sha256:x',
            actionClasses: ['a'],
        )));

        $this->assertSame(0, HumanReview::query()->count());
        $this->assertSame(0, RiskRegisterEntry::query()->count());
    }
}
