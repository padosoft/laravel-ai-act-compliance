<?php

namespace Padosoft\AiActCompliance;

use Illuminate\Support\ServiceProvider;

class AiActComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-act-compliance.php', 'ai-act-compliance');
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

        if (config('ai-act-compliance.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        $this->app['router']->aliasMiddleware('ai-act.disclosure', Disclosure\AiDisclosureMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.consent', Consent\RequireConsentMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.rate-limit', Cybersecurity\PerUserRateLimitMiddleware::class);
        $this->app['router']->aliasMiddleware('ai-act.session-anomaly', Cybersecurity\SessionAnomalyDetectionMiddleware::class);
    }
}
