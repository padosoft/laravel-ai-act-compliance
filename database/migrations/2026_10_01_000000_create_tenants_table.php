<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            // Stable string identifier used as `tenant_id` on every
            // tenant-aware table (alert_routes, bias_snapshots,
            // regulatory_amendments, ...). Globally unique — no two
            // tenants share a slug. Note this is NOT scoped by parent
            // org because the package targets per-tenant isolation
            // (one row per customer), not a hierarchical multi-tenancy.
            // 50 chars matches the smallest tenant_id column already
            // shipped by the package (alert_routes / alert_dispatches);
            // a longer slug would silently truncate when inserted as
            // tenant_id and break cross-table lookups. Copilot iter-1
            // review on PR #5.
            $table->string('slug', 50)->unique('uq_tenants_slug');
            $table->string('name', 200);
            // free | team | enterprise — drives per-tenant quotas
            // and feature gates. The package itself does not enforce
            // tiers; the host app reads `Tenant::tier` and decides.
            $table->string('subscription_tier', 20)->default('team');
            // active | suspended | archived — `suspended` is the
            // soft-disable for billing / compliance freezes;
            // `archived` is GDPR-retention end-state (PII purged,
            // row retained as proof-of-existence).
            $table->string('status', 20)->default('active')->index();
            $table->string('dpo_email', 200)->nullable();
            $table->string('contact_email', 200)->nullable();
            // Per-tenant override map. Dot-notation keys layered over
            // the host config at request time by TenantConfigResolver.
            // Example: {"bias.disparity_threshold": 0.03}.
            $table->json('config_overrides_json')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
