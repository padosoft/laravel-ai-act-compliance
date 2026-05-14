<?php

namespace Padosoft\AiActCompliance;

use LogicException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
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

        $this->app->singleton(CohortParityMetric::class, static fn (): CohortParityMetric => new class implements CohortParityMetric
        {
            public function compute(array $context = []): array
            {
                throw new LogicException('Bind an implementation of ' . CohortParityMetric::class . ' before capturing bias snapshots.');
            }
        });
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

        $this->app['router']->aliasMiddleware('ai-act.disclosure', Disclosure\AiDisclosureMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.consent', Consent\RequireConsentMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.rate-limit', Cybersecurity\PerUserRateLimitMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.session-anomaly', Cybersecurity\SessionAnomalyDetectionMiddleware::class);
    }
}
