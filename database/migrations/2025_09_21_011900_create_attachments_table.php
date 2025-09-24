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
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('email_message_id')->constrained('email_messages')->cascadeOnDelete();

            $table->string('filename'); // Original filename.
            $table->string('mime', 128)->nullable(); // e.g., "text/plain","application/pdf","text/csv".
            $table->unsignedBigInteger('size_bytes')->nullable(); // Raw byte size as received.

            $table->string('storage_disk')->default('attachments'); // Laravel disk name.
            $table->string('storage_path'); // Relative path on the disk.

            // Scanning & extraction lifecycle
            $table->string('scan_status', 16)->default('pending'); // "pending" | "clean" | "infected" | "error"
            $table->string('scan_result')->nullable(); // Virus signature name if infected.
            $table->timestampTz('scanned_at')->nullable();

            $table->string('extract_status', 16)->nullable(); // "queued" | "done" | "error" | null (not applicable)
            $table->json('extract_result_json')->nullable(); // jsonb: extraction results.
            $table->timestampTz('extracted_at')->nullable();

            $table->text('summary_text')->nullable(); // LLM-generated summary.
            $table->timestampTz('summarized_at')->nullable();

            // Additional metadata and summarization payloads used by services/models
            $table->json('meta_json')->nullable();
            $table->json('summarize_json')->nullable();

            $table->timestampsTz();
            $table->index('email_message_id');
            $table->index(['scan_status', 'extract_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
