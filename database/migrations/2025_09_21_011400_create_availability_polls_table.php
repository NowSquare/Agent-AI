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
        Schema::create('availability_polls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();

            $table->json('options_json'); // jsonb: list of ISO8601 datetimes (UTC).
            $table->string('status', 32)->default('open'); // "open" | "closed"
            $table->timestampTz('closed_at')->nullable();

            $table->timestampsTz();
            $table->index('thread_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_polls');
    }
};
