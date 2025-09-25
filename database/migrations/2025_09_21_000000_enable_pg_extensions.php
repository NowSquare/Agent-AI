<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Trigram index support (used for fast LIKE/ILIKE on message_id etc.)
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            // Vector extension for pgvector embeddings
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }
        // GIN indexes on jsonb are supported natively; btree_gin is not required for these migrations.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Prefer safe drop with IF EXISTS; drop vector last if nothing depends on it in dev
            DB::statement('DROP EXTENSION IF EXISTS vector');
        }
    }
};
