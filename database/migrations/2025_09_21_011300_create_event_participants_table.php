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
        Schema::create('event_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('type', 16); // "user" | "contact"
            $table->char('user_id', 26)->nullable();
            $table->char('contact_id', 26)->nullable();
            $table->string('response', 16)->nullable(); // "accepted" | "declined" | "tentative" | null

            $table->timestampsTz();
            $table->index('event_id');
        });

        // Partial unique constraints to prevent duplicate participation.
        DB::statement("CREATE UNIQUE INDEX event_participants_user_unique ON event_participants (event_id, user_id) WHERE type = 'user' AND user_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX event_participants_contact_unique ON event_participants (event_id, contact_id) WHERE type = 'contact' AND contact_id IS NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
