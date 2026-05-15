<?php

namespace Padosoft\AiActCompliance;

use LogicException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\UnboundPlaceholderMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Services\DimensionRegistry;
use Padosoft\AiActCompliance\BiasMonitoring\Services\MetricRegistry;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter;

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
    }
}
