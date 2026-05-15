<?php

namespace Padosoft\AiActCompliance\Tests\Unit\MultiTenancy;

use Padosoft\AiActCompliance\MultiTenancy\Models\Tenant;
use Padosoft\AiActCompliance\MultiTenancy\Services\TenantConfigResolver;
use Padosoft\AiActCompliance\MultiTenancy\Services\TenantContext;
use Padosoft\AiActCompliance\Tests\TestCase;

class TenantConfigResolverTest extends TestCase
{
    public function test_falls_back_to_host_config_when_no_tenant_set(): void
    {
        config()->set('ai-act-compliance.bias.disparity_threshold', 0.05);

        $resolver = $this->app->make(TenantConfigResolver::class);

        self::assertSame(0.05, $resolver->resolve('bias.disparity_threshold'));
    }

    public function test_returns_caller_default_when_host_key_missing(): void
    {
        // The package config doesn't define `made_up_key_xyz`; with no
        // tenant set and no host override, the resolver MUST return
        // the caller-supplied default.
        $resolver = $this->app->make(TenantConfigResolver::class);

        self::assertSame(42, $resolver->resolve('made_up_key_xyz', 42));
    }

    public function test_tenant_override_wins_over_host_config(): void
    {
        config()->set('ai-act-compliance.bias.disparity_threshold', 0.05);
        $tenant = Tenant::query()->create([
            'slug' => 'acme',
            'name' => 'Acme',
            'config_overrides_json' => ['bias.disparity_threshold' => 0.02],
        ]);

        $context = $this->app->make(TenantContext::class);
        $context->set($tenant);
        $resolver = $this->app->make(TenantConfigResolver::class);

        self::assertSame(0.02, $resolver->resolve('bias.disparity_threshold'));
    }

    public function test_tenant_with_no_override_inherits_host_config(): void
    {
        config()->set('ai-act-compliance.bias.disparity_threshold', 0.05);
        $tenant = Tenant::query()->create([
            'slug' => 'acme',
            'name' => 'Acme',
            // no config_overrides_json
        ]);

        $context = $this->app->make(TenantContext::class);
        $context->set($tenant);
        $resolver = $this->app->make(TenantConfigResolver::class);

        self::assertSame(0.05, $resolver->resolve('bias.disparity_threshold'));
    }

    public function test_partial_tenant_override_only_covers_listed_keys(): void
    {
        // Tenant overrides bias.disparity_threshold but leaves
        // alerting.throttle.per_cohort_minutes alone — the resolver
        // returns the host config for the unlisted key, not null.
        config()->set('ai-act-compliance.bias.disparity_threshold', 0.05);
        config()->set('ai-act-compliance.alerting.throttle.per_cohort_minutes', 60);

        $tenant = Tenant::query()->create([
            'slug' => 'acme',
            'name' => 'Acme',
            'config_overrides_json' => ['bias.disparity_threshold' => 0.02],
        ]);
        $context = $this->app->make(TenantContext::class);
        $context->set($tenant);
        $resolver = $this->app->make(TenantConfigResolver::class);

        self::assertSame(0.02, $resolver->resolve('bias.disparity_threshold'));
        self::assertSame(60, $resolver->resolve('alerting.throttle.per_cohort_minutes'));
    }
}
