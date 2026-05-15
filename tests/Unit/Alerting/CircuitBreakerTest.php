<?php

namespace Padosoft\AiActCompliance\Tests\Unit\Alerting;

use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\Alerting\Models\AlertRoute;
use Padosoft\AiActCompliance\Alerting\Services\CircuitBreaker;
use Padosoft\AiActCompliance\Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private function makeRoute(): AlertRoute
    {
        return AlertRoute::query()->create([
            'tenant_id' => 'tenant-a',
            'channel' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/services/foo',
            'enabled' => true,
        ]);
    }

    public function test_fresh_route_is_not_tripped(): void
    {
        $breaker = new CircuitBreaker(failuresToTrip: 3, cooldownMinutes: 30);
        $route = $this->makeRoute();

        self::assertFalse($breaker->isTripped($route));
    }

    public function test_consecutive_failures_below_threshold_do_not_trip(): void
    {
        $breaker = new CircuitBreaker(failuresToTrip: 3, cooldownMinutes: 30);
        $route = $this->makeRoute();

        $breaker->record($route, success: false);
        $route->refresh();
        $breaker->record($route, success: false);
        $route->refresh();

        self::assertFalse($breaker->isTripped($route));
        self::assertSame(2, $route->consecutive_failures);
    }

    public function test_threshold_failures_trip_the_circuit(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $breaker = new CircuitBreaker(failuresToTrip: 3, cooldownMinutes: 30);
        $route = $this->makeRoute();

        for ($i = 0; $i < 3; $i++) {
            $breaker->record($route, success: false);
            $route->refresh();
        }

        self::assertTrue($breaker->isTripped($route));
        self::assertSame(3, $route->consecutive_failures);
        self::assertNotNull($route->tripped_until);

        Carbon::setTestNow();
    }

    public function test_success_resets_consecutive_failures_and_clears_trip(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $breaker = new CircuitBreaker(failuresToTrip: 3, cooldownMinutes: 30);
        $route = $this->makeRoute();

        for ($i = 0; $i < 3; $i++) {
            $breaker->record($route, success: false);
            $route->refresh();
        }
        self::assertTrue($breaker->isTripped($route));

        $breaker->record($route, success: true);
        $route->refresh();

        self::assertFalse($breaker->isTripped($route));
        self::assertSame(0, $route->consecutive_failures);
        self::assertNull($route->tripped_until);

        Carbon::setTestNow();
    }

    public function test_cooldown_expires_trip_naturally(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $breaker = new CircuitBreaker(failuresToTrip: 2, cooldownMinutes: 15);
        $route = $this->makeRoute();

        $breaker->record($route, success: false);
        $route->refresh();
        $breaker->record($route, success: false);
        $route->refresh();

        self::assertTrue($breaker->isTripped($route));

        // 16 min later, cooldown expired
        Carbon::setTestNow('2026-08-01 10:16:00');
        self::assertFalse($breaker->isTripped($route));

        Carbon::setTestNow();
    }
}
