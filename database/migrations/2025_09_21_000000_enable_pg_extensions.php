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
        // Trigram index support (used for fast LIKE/ILIKE on message_id etc.)
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        // GIN indexes on jsonb are supported natively; btree_gin is not required for these migrations.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
