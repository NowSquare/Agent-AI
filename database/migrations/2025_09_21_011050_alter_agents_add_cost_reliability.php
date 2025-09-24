<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Rough token/time cost hint used in allocation scoring (higher = more expensive)
            $table->unsignedInteger('cost_hint')->default(100)->after('capabilities_json');
            // Rolling reliability (0..1); updated after runs via moving average
            $table->decimal('reliability', 3, 2)->default(0.80)->after('cost_hint');
            $table->unsignedInteger('reliability_samples')->default(0)->after('reliability');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['cost_hint','reliability','reliability_samples']);
        });
    }
};


