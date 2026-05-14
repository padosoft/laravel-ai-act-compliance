<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('human_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('state')->default('pending');
            $table->text('review_notes')->nullable();
            $table->string('reviewer_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_reviews');
    }
};
