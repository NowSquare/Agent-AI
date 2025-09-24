<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->json('state')->nullable(); // jsonb: plan/blackboard
            $table->integer('round_no')->default(0);
            $table->timestampsTz();

            $table->index(['account_id','thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};


