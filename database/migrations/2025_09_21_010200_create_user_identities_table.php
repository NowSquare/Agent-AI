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
        Schema::create('user_identities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32); // "email" | "phone" | "oidc"
            $table->string('identifier'); // e.g., email address.
            $table->timestampTz('verified_at')->nullable(); // Set when ownership was confirmed.
            $table->boolean('primary')->default(false); // True if default identity for user.
            $table->timestampsTz();
            $table->unique(['type','identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_identities');
    }
};
