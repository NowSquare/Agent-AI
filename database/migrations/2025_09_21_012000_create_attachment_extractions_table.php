<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What this file does — Stores extracted text from attachments plus embeddings for search.
 * Plain: We keep a short text excerpt and a number list (embedding) so we can find files by meaning.
 * For engineers: `dim` must match the embedding model; IVFFlat + vector_cosine_ops supports cosine KNN.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attachment_extractions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attachment_id')->constrained('email_attachments')->cascadeOnDelete();

            $table->longText('text_excerpt')->nullable(); // First N KB of text for LLM context (truncated).
            $table->string('text_disk')->nullable(); // Disk where the full text is stored (optional).
            $table->string('text_path')->nullable(); // Relative path to full text file.
            $table->unsignedBigInteger('text_bytes')->nullable();
            $table->unsignedInteger('pages')->nullable(); // For PDFs.

            $table->json('summary_json')->nullable(); // jsonb: cached summaries (per locale or purpose).

            $table->timestampsTz();
            $table->index('attachment_id');
        });

        // pgvector embedding column and optional IVFFlat index
        $dim = (int) (config('llm.embeddings.dim', 1536));
        DB::statement("ALTER TABLE attachment_extractions ADD COLUMN IF NOT EXISTS text_embedding vector($dim)");
        DB::statement('CREATE INDEX IF NOT EXISTS attachment_extractions_text_embedding_idx 
  ON attachment_extractions USING ivfflat (text_embedding vector_cosine_ops) WITH (lists = 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop embedding index/column if present
        DB::statement('DROP INDEX IF EXISTS attachment_extractions_text_embedding_idx');
        DB::statement('ALTER TABLE attachment_extractions DROP COLUMN IF EXISTS text_embedding');
        Schema::dropIfExists('attachment_extractions');
    }
};
