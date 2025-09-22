<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('subject'); // Canonical subject (normalized).
            $table->ulid('starter_message_id')->nullable();
            $table->json('context_json')->nullable(); // jsonb: rolling summary, state, counters.
            $table->integer('version')->default(1);
            $table->json('version_history')->nullable();
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestampsTz();
            $table->index('account_id');
            $table->index('last_activity_at');
        });
        // Note: GIN index on jsonb requires additional PostgreSQL extensions, added later if needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
