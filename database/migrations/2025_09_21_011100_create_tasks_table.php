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
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->foreignUlid('agent_id')->constrained('agents')->cascadeOnDelete();

            $table->string('status', 32)->default('pending'); // "pending" | "running" | "succeeded" | "failed" | "cancelled"
            $table->json('input_json')->nullable();  // jsonb
            $table->json('result_json')->nullable(); // jsonb
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();

            $table->timestampsTz();
            $table->index(['account_id','thread_id']);
        });

        // Note: GIN index on jsonb requires additional PostgreSQL extensions, added later if needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
