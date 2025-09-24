<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();

            $table->string('name');
            $table->string('role')->nullable(); // Free-form descriptor (e.g., "scheduler", "notifier")
            $table->json('capabilities_json')->nullable(); // jsonb: advertised tool abilities etc.
            // Allocation & reliability metrics (used by AgentRegistry scoring)
            $table->unsignedInteger('cost_hint')->default(100); // Rough token/time cost hint (higher = more expensive)
            $table->decimal('reliability', 3, 2)->default(0.80); // Rolling average (0..1)
            $table->unsignedInteger('reliability_samples')->default(0); // Sample count for reliability

            $table->timestampsTz();
            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
