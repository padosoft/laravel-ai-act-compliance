<?php

namespace Padosoft\AiActCompliance\Tests\Feature;

use LogicException;
use Illuminate\Support\Facades\Route;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\Consent\RequireConsentMiddleware;
use Padosoft\AiActCompliance\Cybersecurity\PerUserRateLimitMiddleware;
use Padosoft\AiActCompliance\Cybersecurity\SessionAnomalyDetectionMiddleware;
use Padosoft\AiActCompliance\Disclosure\AiDisclosureMiddleware;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter;
use Padosoft\AiActCompliance\Http\Controllers\ComplianceOverviewController;
use Padosoft\AiActCompliance\Tests\TestCase;

class ServiceProviderAndRoutesTest extends TestCase
{
    public function test_overview_endpoint_is_registered_under_the_configured_prefix(): void
    {
        $response = $this->getJson('/api/admin/ai-act-compliance/overview');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('service', 'ai-act-compliance')
            ->assertJsonStructure([
                'kpi' => [
                    'dsar_open',
                    'incidents_open',
                    'consent_granted',
                    'risks_open',
                    'bias_snapshots',
                    'attestations',
                ],
            ]);

        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->uri() === 'api/admin/ai-act-compliance/overview');

        self::assertNotNull($route);
        self::assertStringContainsString(ComplianceOverviewController::class, $route->getActionName());
    }

    public function test_all_screen_api_endpoints_are_registered_and_reachable(): void
    {
        $endpoints = [
            '/api/admin/ai-act-compliance/settings',
            '/api/admin/ai-act-compliance/dsar',
            '/api/admin/ai-act-compliance/consent',
            '/api/admin/ai-act-compliance/risks',
            '/api/admin/ai-act-compliance/incidents',
            '/api/admin/ai-act-compliance/bias',
            '/api/admin/ai-act-compliance/human-reviews',
            '/api/admin/ai-act-compliance/attestations',
        ];

        foreach ($endpoints as $uri) {
            $this->getJson($uri)->assertOk();
        }
    }

    public function test_service_provider_registers_package_middleware_aliases(): void
    {
        $middleware = $this->app['router']->getMiddleware();

        self::assertSame(AiDisclosureMiddleware::class, $middleware['ai-act.disclosure']);
        self::assertSame(RequireConsentMiddleware::class, $middleware['ai-act.consent']);
        self::assertSame(PerUserRateLimitMiddleware::class, $middleware['ai-act.rate-limit']);
        self::assertSame(SessionAnomalyDetectionMiddleware::class, $middleware['ai-act.session-anomaly']);
    }

    public function test_default_contract_bindings_fail_with_clear_messages(): void
    {
        $expectations = [
            [UserDataExporter::class, 'export', [new \stdClass()], 'Bind an implementation of ' . UserDataExporter::class . ' before using DSAR exports.'],
            [UserDataDeleter::class, 'delete', [new \stdClass()], 'Bind an implementation of ' . UserDataDeleter::class . ' before using DSAR deletions.'],
            [CohortParityMetric::class, 'compute', [[]], 'Bind an implementation of ' . CohortParityMetric::class . ' before capturing bias snapshots.'],
        ];

        foreach ($expectations as [$abstract, $method, $arguments, $message]) {
            try {
                $this->app->make($abstract)->{$method}(...$arguments);
                self::fail('Expected the default contract binding to throw.');
            } catch (LogicException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}
