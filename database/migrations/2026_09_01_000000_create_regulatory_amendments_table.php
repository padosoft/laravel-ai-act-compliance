<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regulatory_amendments', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 100)->nullable()->index();
            // The driver key persisted on the row identifies WHICH
            // upstream feed produced it, so a tenant subscribed to
            // multiple feeds can tell amendments apart.
            $table->string('source_driver', 50);
            // Stable upstream identifier (RSS <guid>, Atom <id>, or
            // a SHA-256 of source_url+title for feeds without a
            // stable id). The composite UNIQUE on
            // (source_driver, external_id) is the idempotency
            // anchor — re-polling the same feed never duplicates.
            $table->string('external_id', 191);
            $table->string('source_url', 1024);
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->text('body')->nullable();
            $table->json('impacted_clauses_json')->nullable();
            // pending | triaged | resolved | ignored
            $table->string('status', 20)->default('pending')->index();
            // low | medium | high | critical — derived from clause
            // hits (Art. 5 / Art. 9 etc. weight) at ingest time;
            // operator can override on triage.
            $table->string('severity', 20)->default('low')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('ingested_at');
            $table->timestamp('triaged_at')->nullable();
            $table->string('triaged_by', 100)->nullable();
            $table->text('triage_notes')->nullable();
            $table->timestamps();

            // Per-tenant idempotency: two tenants polling the same
            // upstream feed can legitimately each store their own row
            // for the same external_id. Cross-tenant deduplication
            // would silently drop tenant B's amendment. Copilot iter-1
            // review on PR #4. SQLite stores NULL as distinct in
            // UNIQUE (unlike Postgres NULLS NOT DISTINCT pre-15), so
            // global (tenant_id IS NULL) rows still dedupe correctly.
            $table->unique(['tenant_id', 'source_driver', 'external_id'], 'uq_reg_amend_tenant_driver_extid');
            $table->index(['tenant_id', 'status', 'severity'], 'idx_reg_amend_tenant_status_sev');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_amendments');
    }
};
