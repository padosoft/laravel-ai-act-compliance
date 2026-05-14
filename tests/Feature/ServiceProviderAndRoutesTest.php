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
        // DSAR contracts retain the v1.0 placeholder-with-throw pattern
        // — there is no sensible default exporter / deleter; host apps
        // MUST bind one.
        $expectations = [
            [UserDataExporter::class, 'export', [new \stdClass()], 'Bind an implementation of ' . UserDataExporter::class . ' before using DSAR exports.'],
            [UserDataDeleter::class, 'delete', [new \stdClass()], 'Bind an implementation of ' . UserDataDeleter::class . ' before using DSAR deletions.'],
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

    public function test_cohort_parity_metric_default_binding_resolves_to_configured_default_metric_v12(): void
    {
        // v1.2 — CohortParityMetric now resolves to the metric the
        // host configures under `bias.default_metric` instead of the
        // pre-v1.2 placeholder that threw LogicException. This keeps
        // a fresh host that only configures the registry (no explicit
        // binding) from crashing on capture().
        $metric = $this->app->make(CohortParityMetric::class);

        self::assertInstanceOf(
            \Padosoft\AiActCompliance\BiasMonitoring\Metrics\DemographicParityMetric::class,
            $metric,
        );
    }
}
