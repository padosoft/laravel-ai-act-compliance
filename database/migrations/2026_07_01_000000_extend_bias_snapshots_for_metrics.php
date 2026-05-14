<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bias_snapshots', function (Blueprint $table): void {
            $table->string('metric_name', 64)->default('demographic_parity')->after('cohort');
            $table->string('metric_version', 16)->default('1.0')->after('metric_name');
            $table->string('cohort_dimension', 64)->nullable()->after('metric_version');
            $table->json('article_evidence_json')->nullable()->after('payload');
            $table->decimal('disparity_score', 8, 6)->nullable()->after('article_evidence_json');
        });

        // Composite index for the most common admin-query shape:
        // \"all snapshots for tenant X, metric Y, dimension Z, recent first\".
        Schema::table('bias_snapshots', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'metric_name', 'cohort_dimension'],
                'idx_bias_snap_tenant_metric_dim',
            );
        });
    }

    public function down(): void
    {
        Schema::table('bias_snapshots', function (Blueprint $table): void {
            $table->dropIndex('idx_bias_snap_tenant_metric_dim');
        });

        Schema::table('bias_snapshots', function (Blueprint $table): void {
            $table->dropColumn([
                'metric_name',
                'metric_version',
                'cohort_dimension',
                'article_evidence_json',
                'disparity_score',
            ]);
        });
    }
};
