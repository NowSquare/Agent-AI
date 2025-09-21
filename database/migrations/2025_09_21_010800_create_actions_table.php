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
        Schema::create('actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();

            $table->string('type', 64);
            // Allowed: "approve","reject","revise","select_option","provide_value",
            // "schedule_propose_times","schedule_confirm","unsubscribe","info_request","stop"

            $table->json('payload_json')->nullable(); // jsonb: action parameters as validated by schema.
            $table->string('status', 32)->default('pending'); // "pending" | "completed" | "cancelled" | "failed"
            $table->timestampTz('expires_at')->nullable();   // Signed link expiry if applicable.
            $table->timestampTz('completed_at')->nullable();
            $table->json('error_json')->nullable(); // jsonb: failure diagnostics.

            // Clarification loop state
            $table->unsignedTinyInteger('clarification_rounds')->default(0); // Number of clarification messages sent.
            $table->unsignedTinyInteger('clarification_max')->default(2); // Upper bound (usually 2).
            $table->timestampTz('last_clarification_sent_at')->nullable();

            $table->timestampsTz();
            $table->index(['thread_id','status']);
            $table->index('type');
        });

        // Note: GIN index on jsonb requires additional PostgreSQL extensions, added later if needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
