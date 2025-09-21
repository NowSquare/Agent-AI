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
        Schema::create('availability_votes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('availability_polls')->cascadeOnDelete();

            $table->string('type', 16); // "user" | "contact"
            $table->char('user_id', 26)->nullable();
            $table->char('contact_id', 26)->nullable();
            $table->json('choices_json'); // jsonb: either indices or ISO8601 set.

            $table->timestampsTz();
            $table->index('poll_id');
        });

        // Ensure one vote per (poll, user) or (poll, contact).
        DB::statement("CREATE UNIQUE INDEX availability_votes_user_unique ON availability_votes (poll_id, user_id) WHERE type = 'user' AND user_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX availability_votes_contact_unique ON availability_votes (poll_id, contact_id) WHERE type = 'contact' AND contact_id IS NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_votes');
    }
};
