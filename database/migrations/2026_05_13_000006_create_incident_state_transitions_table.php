<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incident_state_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_ticket_id')->constrained('incident_tickets')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('actor_id')->nullable();
            $table->timestamp('transitioned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_state_transitions');
    }
};
