<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->foreignUlid('email_message_id')->nullable()->constrained('email_messages')->nullOnDelete();
            $table->foreignUlid('action_id')->nullable()->constrained('actions')->nullOnDelete();
            $table->foreignUlid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('role', 16); // CHECK via app logic: CLASSIFY|GROUNDED|SYNTH|TOOL
            $table->string('provider', 40);
            $table->string('model', 80);
            $table->string('step_type', 32); // chat|tool|route|summary
            $table->json('input_json')->nullable();
            $table->json('output_json')->nullable();
            $table->integer('tokens_input')->default(0);
            $table->integer('tokens_output')->default(0);
            $table->integer('tokens_total')->default(0);
            $table->integer('latency_ms')->default(0);
            $table->decimal('confidence', 3, 2)->nullable();
            // Multi-agent protocol fields
            $table->string('agent_role', 16)->nullable(); // Planner|Worker|Critic|Arbiter
            $table->integer('round_no')->default(0);
            $table->foreignUlid('coalition_id')->nullable();
            $table->decimal('vote_score', 4, 2)->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestampsTz();

            $table->index(['thread_id', 'created_at']);
            $table->index(['role', 'created_at']);
            $table->index(['provider', 'model']);
            $table->index(['agent_role','round_no']);
            $table->index('contact_id');
            $table->index('user_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_steps');
    }
};


