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
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name'); // Friendly name for the token (e.g., "MCP Agent A").
            $table->string('token_hash', 64)->unique(); // SHA-256 hex of the token.
            $table->json('abilities')->nullable(); // jsonb: list of allowed tool names/actions.

            // Optional scoping of a token to a user and/or an account.
            $table->char('user_id', 26)->nullable();
            $table->char('account_id', 26)->nullable();

            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id','account_id']);
        });

        // Enforce at least one scope (user or account) if your policy requires it:
        // DB::statement("ALTER TABLE api_tokens ADD CONSTRAINT api_tokens_scope_chk CHECK (user_id IS NOT NULL OR account_id IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
