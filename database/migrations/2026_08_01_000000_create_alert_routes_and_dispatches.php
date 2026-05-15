<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alert_routes', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 50)->nullable()->index();
            $table->string('channel', 32);             // 'slack' | 'discord' | 'email'
            $table->text('webhook_url')->nullable();   // encrypted at app layer (Crypt::encryptString)
            $table->string('email')->nullable();
            $table->json('severity_filter_json')->nullable(); // e.g. ['high','critical']
            $table->boolean('enabled')->default(true);
            $table->dateTime('last_success_at')->nullable();
            $table->dateTime('last_failure_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->dateTime('tripped_until')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'channel'], 'uq_alert_routes_tenant_channel');
        });

        Schema::create('alert_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 50)->nullable()->index();
            $table->foreignId('alert_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 32);
            $table->string('severity', 16);
            $table->string('title');
            // Denormalised from payload_json so the throttler can
            // query (tenant_id, channel, cohort, ok, created_at)
            // without relying on JSON1 (which is optional on
            // SQLite + may be missing on older Alpine builds —
            // Copilot review on PR #3 caught the portability hazard).
            $table->string('cohort', 128)->nullable()->index();
            $table->json('payload_json');
            $table->boolean('ok');
            $table->boolean('transient_failure')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'channel', 'cohort', 'created_at'], 'idx_alert_dispatch_throttle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_dispatches');
        Schema::dropIfExists('alert_routes');
    }
};
