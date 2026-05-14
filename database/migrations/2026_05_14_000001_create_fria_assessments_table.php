<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fria_assessments', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('project_key')->nullable();
            $table->string('title');
            $table->text('scope');
            $table->json('risks_json')->nullable();
            $table->json('mitigations_json')->nullable();
            $table->unsignedInteger('review_cadence_days');
            $table->dateTime('next_review_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('opened_by')->nullable();
            $table->string('signed_off_by')->nullable();
            $table->dateTime('signed_off_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fria_assessments');
    }
};
