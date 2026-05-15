<?php

namespace Padosoft\AiActCompliance\Tests\Unit\MultiTenancy;

use Padosoft\AiActCompliance\MultiTenancy\Models\Tenant;
use Padosoft\AiActCompliance\MultiTenancy\Services\TenantContext;
use Padosoft\AiActCompliance\Tests\TestCase;

class TenantContextTest extends TestCase
{
    public function test_starts_unset(): void
    {
        $context = new TenantContext();
        self::assertFalse($context->isSet());
        self::assertNull($context->current());
        self::assertNull($context->currentSlug());
    }

    public function test_activate_resolves_existing_tenant(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'acme',
            'name' => 'Acme Inc.',
        ]);

        $context = new TenantContext();
        $resolved = $context->activate('acme');

        self::assertNotNull($resolved);
        self::assertSame($tenant->id, $resolved->id);
        self::assertTrue($context->isSet());
        self::assertSame('acme', $context->currentSlug());
    }

    public function test_activate_unknown_slug_returns_null_and_clears_context(): void
    {
        $context = new TenantContext();
        $result = $context->activate('does-not-exist');

        self::assertNull($result);
        self::assertFalse($context->isSet());
    }

    public function test_activate_same_slug_twice_reuses_cached_model(): void
    {
        Tenant::query()->create([
            'slug' => 'acme',
            'name' => 'Acme Inc.',
        ]);
        $context = new TenantContext();
        $first = $context->activate('acme');
        $second = $context->activate('acme');

        self::assertSame($first, $second);
    }
}
