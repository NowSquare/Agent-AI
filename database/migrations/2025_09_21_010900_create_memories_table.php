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
        Schema::create('memories', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('scope', 32); // "conversation" | "user" | "account"
            $table->char('scope_id', 26); // ULID of the scope owner.
            $table->string('key'); // Memory key (namespaced string, e.g. "locale.preference").
            $table->json('value_json'); // jsonb: arbitrary structured value.

            $table->float('confidence'); // Range [0,1]
            $table->string('ttl_category', 32); // "volatile" | "seasonal" | "durable" | "legal"
            $table->timestampTz('expires_at')->nullable();

            $table->integer('version')->default(1);
            $table->char('supersedes_id', 26)->nullable(); // Points to previous memory ULID when superseding.
            $table->string('provenance')->nullable(); // e.g., email_message_id or tool reference.

            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->integer('usage_count')->default(0);
            
            $table->json('meta')->nullable(); // Additional metadata (e.g., prompt_key, model)
            $table->string('email_message_id')->nullable();
            $table->char('thread_id', 26)->nullable();
            
            $table->timestampsTz();
            $table->softDeletes('deleted_at', 0);
            
            $table->index(['scope', 'scope_id', 'key']);
            $table->index(['ttl_category', 'expires_at']);
            $table->index(['last_used_at', 'usage_count']);
        });

        // Note: GIN index on jsonb requires additional PostgreSQL extensions, added later if needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
