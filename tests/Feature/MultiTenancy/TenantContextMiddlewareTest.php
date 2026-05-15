<?php

namespace Padosoft\AiActCompliance\Tests\Feature\MultiTenancy;

use Illuminate\Support\Facades\Route;
use Padosoft\AiActCompliance\MultiTenancy\Models\Tenant;
use Padosoft\AiActCompliance\MultiTenancy\Services\TenantContext;
use Padosoft\AiActCompliance\Tests\TestCase;

class TenantContextMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Mount a tiny probe route behind the ai-act.tenant-context
        // middleware to assert context binding without coupling the
        // test to a specific package endpoint. Inline closure keeps
        // the test self-contained.
        Route::middleware('ai-act.tenant-context')
            ->get('/test-tenant-probe', function () {
                $ctx = app(TenantContext::class);

                return response()->json([
                    'slug' => $ctx->currentSlug(),
                ]);
            });
    }

    public function test_no_header_passes_through_with_null_context(): void
    {
        $response = $this->get('/test-tenant-probe');
        $response->assertOk();
        self::assertNull($response->json('slug'));
    }

    public function test_header_resolves_active_tenant_into_context(): void
    {
        Tenant::query()->create([
            'slug' => 'acme',
            'name' => 'Acme Inc.',
        ]);

        $response = $this->withHeaders(['X-Tenant-Id' => 'acme'])->get('/test-tenant-probe');

        $response->assertOk();
        self::assertSame('acme', $response->json('slug'));
    }

    public function test_query_param_fallback_resolves_tenant(): void
    {
        Tenant::query()->create([
            'slug' => 'acme',
            'name' => 'Acme',
        ]);

        $response = $this->get('/test-tenant-probe?tenant=acme');

        $response->assertOk();
        self::assertSame('acme', $response->json('slug'));
    }

    public function test_unknown_tenant_slug_returns_404(): void
    {
        $response = $this->withHeaders(['X-Tenant-Id' => 'ghost'])->get('/test-tenant-probe');
        $response->assertStatus(404);
        self::assertSame('tenant not found', $response->json('error'));
    }

    public function test_suspended_tenant_returns_423(): void
    {
        Tenant::query()->create([
            'slug' => 'frozen',
            'name' => 'Frozen Co',
            'status' => 'suspended',
        ]);
        $response = $this->withHeaders(['X-Tenant-Id' => 'frozen'])->get('/test-tenant-probe');
        $response->assertStatus(423);
        self::assertSame('tenant suspended', $response->json('error'));
    }

    public function test_archived_tenant_returns_410(): void
    {
        Tenant::query()->create([
            'slug' => 'gone',
            'name' => 'Gone Co',
            'status' => 'archived',
        ]);
        $response = $this->withHeaders(['X-Tenant-Id' => 'gone'])->get('/test-tenant-probe');
        $response->assertStatus(410);
        self::assertSame('tenant archived', $response->json('error'));
    }
}
