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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachment_extractions');
    }
};
