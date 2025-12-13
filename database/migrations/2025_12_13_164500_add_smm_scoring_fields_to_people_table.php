<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('vk_status')->nullable()->after('vk_url'); // ACTIVE 2025, DEAD...
            $table->decimal('lead_score', 5, 2)->nullable()->after('smm_analysis'); // 0-100.00
            $table->string('lead_category')->nullable()->after('lead_score'); // HOT, WARM, COLD...
            $table->text('visual_analysis')->nullable()->after('notes'); // Content from Lisa
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['vk_status', 'lead_score', 'lead_category', 'visual_analysis']);
        });
    }
};
