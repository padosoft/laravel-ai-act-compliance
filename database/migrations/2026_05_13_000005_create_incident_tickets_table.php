<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incident_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('title');
            $table->string('severity')->default('low');
            $table->string('status')->default('open');
            $table->text('description')->nullable();
            $table->string('owner_id')->nullable();
            $table->json('article_refs')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_tickets');
    }
};
