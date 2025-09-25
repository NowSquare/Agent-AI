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
        Schema::create('email_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();

            $table->string('direction', 16); // "inbound" | "outbound"
            $table->string('processing_status', 32)->default('received'); // "received" | "queued" | "processing" | "processed" | "failed"
            $table->string('message_id')->unique(); // RFC 5322 Message-ID.
            $table->string('in_reply_to')->nullable()->index();
            $table->string('references', 2048)->nullable(); // Space-separated list.

            // Addressing; arrays serialized as jsonb for consistency.
            $table->string('from_email')->nullable(); // Lower-case email.
            $table->string('from_name')->nullable();
            $table->json('to_json')->nullable();   // jsonb: array of {"email","name"}
            $table->json('cc_json')->nullable();   // jsonb
            $table->json('bcc_json')->nullable();  // jsonb

            $table->string('subject')->nullable(); // Optional per-message subject override.
            $table->json('headers_json')->nullable(); // jsonb: selected headers for quick access.

            // Provider metadata (mainly for outbound delivery status).
            $table->string('provider_message_id')->nullable(); // e.g. Postmark MessageID.
            $table->string('delivery_status', 32)->nullable(); // "queued" | "sent" | "bounced" | "failed" | null for inbound.
            $table->json('delivery_error_json')->nullable();   // jsonb: provider error payload.

            // Bodies
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            // Misc
            $table->string('x_thread_id')->nullable(); // Internal hint header.
            $table->unsignedBigInteger('raw_size_bytes')->nullable();
            $table->timestampTz('processed_at')->nullable(); // When LLM processing completed.

            $table->timestampsTz();
            $table->index('thread_id');
            $table->index(['direction', 'delivery_status']);
            $table->index(['direction', 'processing_status']);
            $table->index('from_email');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Optional trigram index for fast substring search on message_id.
            DB::statement('CREATE INDEX IF NOT EXISTS email_messages_message_id_trgm ON email_messages USING GIN (message_id gin_trgm_ops)');

            // pgvector embedding column and optional IVFFlat index
            $dim = (int) (config('llm.embeddings.dim', 1536));
            DB::statement("ALTER TABLE email_messages ADD COLUMN IF NOT EXISTS body_embedding vector($dim)");
            DB::statement('CREATE INDEX IF NOT EXISTS email_messages_body_embedding_idx 
  ON email_messages USING ivfflat (body_embedding vector_cosine_ops) WITH (lists = 100)');
        }

        // Add foreign key constraint to threads table now that email_messages exists
        Schema::table('threads', function (Blueprint $table) {
            $table->foreign('starter_message_id')->references('id')->on('email_messages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Drop embedding index/column if present
            DB::statement('DROP INDEX IF EXISTS email_messages_body_embedding_idx');
            DB::statement('ALTER TABLE email_messages DROP COLUMN IF EXISTS body_embedding');
        }
        // Remove foreign key constraint from threads table first
        Schema::table('threads', function (Blueprint $table) {
            $table->dropForeign(['starter_message_id']);
        });

        Schema::dropIfExists('email_messages');
    }
};
