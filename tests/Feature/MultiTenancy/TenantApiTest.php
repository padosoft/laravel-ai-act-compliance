<?php

namespace Padosoft\AiActCompliance\Tests\Feature\MultiTenancy;

use Padosoft\AiActCompliance\MultiTenancy\Enums\TenantStatus;
use Padosoft\AiActCompliance\MultiTenancy\Models\Tenant;
use Padosoft\AiActCompliance\Tests\TestCase;

class TenantApiTest extends TestCase
{
    private const URL_BASE = '/api/admin/ai-act-compliance/tenants';

    public function test_index_returns_tenants_and_platform_totals(): void
    {
        Tenant::query()->create(['slug' => 'acme', 'name' => 'Acme']);
        Tenant::query()->create(['slug' => 'globex', 'name' => 'Globex', 'status' => 'suspended']);

        $response = $this->getJson(self::URL_BASE);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'tenants',
                'totals' => [
                    'tenants_total',
                    'tenants_active',
                    'tenants_suspended',
                ],
            ],
        ]);
        self::assertSame(2, $response->json('data.totals.tenants_total'));
        self::assertSame(1, $response->json('data.totals.tenants_active'));
        self::assertSame(1, $response->json('data.totals.tenants_suspended'));
    }

    public function test_store_creates_tenant_with_defaults(): void
    {
        $response = $this->postJson(self::URL_BASE, [
            'slug' => 'newco',
            'name' => 'New Co',
        ]);

        $response->assertStatus(201);
        self::assertDatabaseHas('tenants', [
            'slug' => 'newco',
            'status' => 'active',
            'subscription_tier' => 'team',
        ]);
    }

    public function test_store_rejects_invalid_slug_format(): void
    {
        $response = $this->postJson(self::URL_BASE, [
            'slug' => 'NOT VALID slug!',
            'name' => 'bad',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        Tenant::query()->create(['slug' => 'acme', 'name' => 'Acme']);
        $response = $this->postJson(self::URL_BASE, [
            'slug' => 'acme',
            'name' => 'Acme Duplicate',
        ]);
        $response->assertStatus(422);
    }

    public function test_show_returns_tenant_with_kpis(): void
    {
        Tenant::query()->create(['slug' => 'acme', 'name' => 'Acme']);

        $response = $this->getJson(self::URL_BASE.'/acme');
        $response->assertOk();
        $response->assertJsonPath('data.tenant.slug', 'acme');
        $response->assertJsonStructure(['data' => ['tenant', 'kpis']]);
    }

    public function test_show_404_for_unknown_slug(): void
    {
        $response = $this->getJson(self::URL_BASE.'/ghost');
        $response->assertNotFound();
    }

    public function test_patch_stamps_suspended_at_only_on_first_transition(): void
    {
        $tenant = Tenant::query()->create(['slug' => 'acme', 'name' => 'Acme']);
        self::assertNull($tenant->suspended_at);

        $response = $this->patchJson(self::URL_BASE.'/acme', ['status' => 'suspended']);
        $response->assertOk();
        $tenant->refresh();
        self::assertNotNull($tenant->suspended_at);
        $originalStamp = $tenant->suspended_at;

        // Bounce back to active, then re-suspend — original audit
        // timestamp must be preserved (we don't overwrite a stamp
        // that's already set, mirroring v1.4 controller behaviour).
        $this->patchJson(self::URL_BASE.'/acme', ['status' => 'active']);
        $this->patchJson(self::URL_BASE.'/acme', ['status' => 'suspended']);
        $tenant->refresh();
        self::assertSame(
            $originalStamp->format('Y-m-d H:i:s'),
            $tenant->suspended_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_patch_validates_status_against_enum(): void
    {
        Tenant::query()->create(['slug' => 'acme', 'name' => 'Acme']);
        $response = $this->patchJson(self::URL_BASE.'/acme', ['status' => 'NUCLEAR']);
        $response->assertStatus(422);
    }

    public function test_active_scope_returns_only_active(): void
    {
        Tenant::query()->create(['slug' => 'a', 'name' => 'A']);
        Tenant::query()->create(['slug' => 'b', 'name' => 'B', 'status' => 'archived']);

        $rows = Tenant::query()->active()->get();
        self::assertCount(1, $rows);
        self::assertSame('a', $rows->first()->slug);
        self::assertTrue($rows->first()->isActive());

        $tenantStatusValues = array_map(static fn (TenantStatus $s) => $s->value, TenantStatus::cases());
        self::assertContains('archived', $tenantStatusValues);
    }
}
