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
        Schema::create('agent_specializations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('agent_id', 26);
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('capabilities')->nullable();
            $table->float('confidence_threshold')->default(0.75);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('cascade');

            $table->index(['agent_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_specializations');
    }
};
