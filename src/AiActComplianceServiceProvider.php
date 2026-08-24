<?php

namespace Padosoft\AiActCompliance;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Padosoft\AiActCompliance\Alerting\Events\BiasDriftDetected;
use Padosoft\AiActCompliance\Alerting\Listeners\BiasDriftDetectedListener;
use Padosoft\AiActCompliance\Alerting\Services\AlertDispatcher;
use Padosoft\AiActCompliance\Alerting\Services\AlertThrottler;
use Padosoft\AiActCompliance\Alerting\Services\CircuitBreaker;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\UnboundPlaceholderMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Services\DimensionRegistry;
use Padosoft\AiActCompliance\BiasMonitoring\Services\MetricRegistry;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter;
use Padosoft\AiActCompliance\MultiTenancy\Http\Middleware\TenantContextMiddleware;
use Padosoft\AiActCompliance\MultiTenancy\Services\CrossTenantOverviewService;
use Padosoft\AiActCompliance\MultiTenancy\Services\TenantConfigResolver;
use Padosoft\AiActCompliance\MultiTenancy\Services\TenantContext;
use Padosoft\AiActCompliance\RegulatoryFeed\Commands\PollRegulatoryFeedCommand;
use Padosoft\AiActCompliance\RegulatoryFeed\Services\ImpactedClauseDetector;
use Padosoft\AiActCompliance\RegulatoryFeed\Services\RegulatoryFeedPoller;

class AiActComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-act-compliance.php', 'ai-act-compliance');

        $this->app->singleton(UserDataExporter::class, static fn (): UserDataExporter => new class implements UserDataExporter
        {
            public function export(object $user): array
            {
                throw new LogicException('Bind an implementation of ' . UserDataExporter::class . ' before using DSAR exports.');
            }
        });

        $this->app->singleton(UserDataDeleter::class, static fn (): UserDataDeleter => new class implements UserDataDeleter
        {
            public function delete(object $user): void
            {
                throw new LogicException('Bind an implementation of ' . UserDataDeleter::class . ' before using DSAR deletions.');
            }
        });

        // Bind a SENTINEL placeholder (a named class — not an anonymous
        // class — so `boot()` can detect via `instanceof` whether the
        // host has overridden the binding). The placeholder still
        // throws on `compute()` for hosts that neither bind their own
        // CohortParityMetric NOR configure `bias.default_metric`.
        $this->app->singleton(CohortParityMetric::class, UnboundPlaceholderMetric::class);

        // v1.2 — pluggable metric registry. Singleton so host apps can
        // call register() during their boot() and the binding survives
        // across requests in long-lived workers.
        $this->app->singleton(MetricRegistry::class);
        $this->app->singleton(DimensionRegistry::class);

        // v1.3 — alerting cascade services. Throttler + circuit
        // breaker have config-driven constants so they bind via
        // factory closures.
        $this->app->singleton(AlertThrottler::class, static fn ($app) => new AlertThrottler(
            (int) $app['config']->get('ai-act-compliance.alerting.throttle.per_cohort_minutes', 60),
        ));
        $this->app->singleton(CircuitBreaker::class, static fn ($app) => new CircuitBreaker(
            failuresToTrip: (int) $app['config']->get('ai-act-compliance.alerting.circuit_breaker.failures_to_trip', 5),
            cooldownMinutes: (int) $app['config']->get('ai-act-compliance.alerting.circuit_breaker.cooldown_minutes', 30),
        ));
        $this->app->singleton(AlertDispatcher::class);

        // v1.4 — regulatory-feed services. Detector pattern map flows
        // from config so hosts can extend / override without forking
        // the package.
        $this->app->singleton(ImpactedClauseDetector::class, static fn ($app) => new ImpactedClauseDetector(
            (array) $app['config']->get('ai-act-compliance.regulatory_feed.impacted_clause_patterns', []),
        ));
        $this->app->singleton(RegulatoryFeedPoller::class);

        // v1.5 — multi-tenancy. TenantContext holds MUTABLE per-request
        // state (the active Tenant). Bind as `scoped` so Octane / queue
        // workers / long-lived processes flush it between requests
        // automatically; a `singleton` here would leak the previously-
        // resolved tenant into the next request. Copilot iter-1 review
        // on PR #5.
        $this->app->scoped(TenantContext::class);
        // Resolver + read-only services are safe as singletons — they
        // delegate to TenantContext for per-request state.
        $this->app->singleton(TenantConfigResolver::class);
        $this->app->singleton(CrossTenantOverviewService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/ai-act-compliance.php' => config_path('ai-act-compliance.php'),
        ], 'ai-act-compliance-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ai-act-compliance-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (! config('ai-act-compliance.enabled', true)) {
            return;
        }

        if (config('ai-act-compliance.routes.enabled', true)) {
            Route::middleware(config('ai-act-compliance.routes.middleware', []))
                ->prefix(trim((string) config('ai-act-compliance.routes.prefix', ''), '/'))
                ->group(__DIR__ . '/../routes/api.php');
        }

        // v1.2 — seed MetricRegistry from config. FQCN validation runs
        // per-binding (R23 pluggable-pipeline-registry) so misconfigured
        // hosts fail loudly at boot rather than silently picking the
        // wrong handler at request time.
        $registry = $this->app->make(MetricRegistry::class);
        foreach ((array) config('ai-act-compliance.bias.metrics', []) as $name => $fqcn) {
            if (! is_string($name) || ! is_string($fqcn)) {
                continue;
            }
            if (! $registry->has($name)) {
                $registry->register($name, $fqcn);
            }
        }

        // v1.2 — when `bias.default_metric` resolves cleanly AND the
        // current CohortParityMetric binding is still the SP-supplied
        // sentinel placeholder, rebind to the registry's default
        // metric. Effect: a host that ONLY configures
        // `bias.default_metric` + `bias.metrics` (without binding its
        // own CohortParityMetric) gets the configured default
        // automatically instead of the placeholder that throws on
        // capture(). The `instanceof` check is what guarantees a host
        // that bound its own CohortParityMetric in its
        // AppServiceProvider::register() (which runs AFTER this
        // provider's register() but BEFORE this boot() per Laravel's
        // two-phase provider lifecycle) is NOT silently overwritten —
        // Copilot review on PR #2 (commit 19d2a6a) caught this race.
        $defaultMetricName = (string) config('ai-act-compliance.bias.default_metric', '');
        if ($defaultMetricName !== '' && $registry->has($defaultMetricName)) {
            $currentBinding = $this->app->make(CohortParityMetric::class);
            if ($currentBinding instanceof UnboundPlaceholderMetric) {
                $this->app->singleton(
                    CohortParityMetric::class,
                    static fn () => $registry->resolve($defaultMetricName),
                );
            }
        }

        $this->app['router']->aliasMiddleware('ai-act.disclosure', Disclosure\AiDisclosureMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.consent', Consent\RequireConsentMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.rate-limit', Cybersecurity\PerUserRateLimitMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.session-anomaly', Cybersecurity\SessionAnomalyDetectionMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.tenant-context', TenantContextMiddleware::class);

        // v1.3 — subscribe the alert listener exactly once per
        // application instance. Without this guard,
        // `php artisan octane:reload` (and any test that re-boots
        // the application within the same process) would register
        // duplicate listeners and dispatch the alert twice. Copilot
        // review on PR #3 caught the misleading \"idempotent\" comment
        // in the earlier version.
        if (! $this->app->bound('ai-act.alerting.listener-registered')) {
            $this->app->make(Dispatcher::class)->listen(
                BiasDriftDetected::class,
                BiasDriftDetectedListener::class,
            );
            $this->app->instance('ai-act.alerting.listener-registered', true);
        }

        // v1.8 — IAM delegated-access bridge (laravel-iam-agents >= 1.1). Double-gated:
        // explicit config opt-in AND the event classes present (`::class` on a missing
        // class is just a string — no autoload — so this whole block is a no-op when
        // the IAM suite is not installed). Same once-per-instance guard as alerting.
        if ($this->app['config']->get('ai-act-compliance.iam_delegation.enabled') === true
            && class_exists(\Padosoft\Iam\Agents\Events\DelegationGrantCreated::class)
            && ! $this->app->bound('ai-act.iam-delegation.listeners-registered')) {
            $events = $this->app->make(Dispatcher::class);
            $events->listen(
                \Padosoft\Iam\Agents\Events\DelegationGrantCreated::class,
                IamDelegation\Listeners\RecordDelegationGrantOversight::class,
            );
            $events->listen(
                \Padosoft\Iam\Agents\Events\DelegationGrantRevoked::class,
                IamDelegation\Listeners\RecordDelegationGrantRevocation::class,
            );
            $events->listen(
                \Padosoft\Iam\Agents\Events\AgentApproved::class,
                IamDelegation\Listeners\RegisterAgentInRiskRegister::class,
            );
            $events->listen(
                \Padosoft\Iam\Agents\Events\AgentSuspended::class,
                IamDelegation\Listeners\UpdateAgentRiskStatus::class,
            );
            $events->listen(
                \Padosoft\Iam\Agents\Events\AgentRetired::class,
                IamDelegation\Listeners\UpdateAgentRiskStatus::class,
            );
            $this->app->instance('ai-act.iam-delegation.listeners-registered', true);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                PollRegulatoryFeedCommand::class,
            ]);
        }
    }
}
