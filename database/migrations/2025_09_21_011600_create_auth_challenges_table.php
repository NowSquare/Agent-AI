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
        Schema::create('auth_challenges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_identity_id')->nullable()->constrained('user_identities')->cascadeOnDelete();

            $table->string('identifier'); // Email or phone used to initiate the challenge.
            $table->string('channel', 16); // "email" | "sms"
            $table->string('code_hash');   // Hash of the verification code.
            $table->string('token')->nullable(); // Opaque magic-link token (signed in URL).
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0); // Incremented on verify attempts.
            $table->ipAddress('ip')->nullable();

            $table->timestampsTz();
            $table->index(['identifier','expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_challenges');
    }
};
