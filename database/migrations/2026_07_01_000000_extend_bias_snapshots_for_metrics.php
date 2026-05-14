<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bias_snapshots', function (Blueprint $table): void {
            // Default to 'legacy' rather than 'demographic_parity' so
            // every pre-v1.2 row (and any host-app custom v1.1 metric
            // that didn't implement NamedCohortMetric) is HONESTLY
            // labelled as unknown-provenance instead of being silently
            // absorbed into the demographic-parity bucket — Copilot
            // review on PR #2 caught the audit-trail contamination.
            $table->string('metric_name', 64)->default('legacy')->after('cohort');
            $table->string('metric_version', 16)->default('1.0')->after('metric_name');
            $table->string('cohort_dimension', 64)->nullable()->after('metric_version');
            $table->json('article_evidence_json')->nullable()->after('payload');
            $table->decimal('disparity_score', 8, 6)->nullable()->after('article_evidence_json');
        });

        // Composite index for the dominant admin-query shape:
        //   \"all snapshots for tenant X, metric Y, dimension Z, recent first\".
        // `created_at` is the 4th key so the recent-first ordering is
        // served from the index without a filesort step on the matched
        // rows — Copilot review on PR #2 flagged the original
        // (tenant_id, metric_name, cohort_dimension) shape as
        // incomplete for the documented access path.
        Schema::table('bias_snapshots', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'metric_name', 'cohort_dimension', 'created_at'],
                'idx_bias_snap_tenant_metric_dim_recent',
            );
        });
    }

    public function down(): void
    {
        Schema::table('bias_snapshots', function (Blueprint $table): void {
            $table->dropIndex('idx_bias_snap_tenant_metric_dim_recent');
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
