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
        Schema::create('email_inbound_payloads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider', 32)->default('postmark'); // Source provider identifier.
            $table->binary('ciphertext'); // Encrypted JSON payload (application-level encryption).
            $table->json('meta_json')->nullable(); // jsonb: signature status, IP, headers subset.
            $table->boolean('signature_verified')->default(false);
            $table->string('remote_ip', 45)->nullable();
            $table->unsignedBigInteger('content_length')->nullable();

            $table->timestampTz('received_at')->index();
            $table->timestampTz('purge_after')->index(); // Usually now()+30 days.

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_inbound_payloads');
    }
};
