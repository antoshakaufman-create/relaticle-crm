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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('industry')->nullable()->after('name');
            $table->string('website')->nullable()->after('industry');
            $table->string('vk_url')->nullable()->after('website');
            $table->string('vk_status')->nullable()->after('vk_url'); // ACTIVE, DEAD
            $table->decimal('lead_score', 5, 2)->nullable()->after('vk_status');
            $table->string('lead_category')->nullable()->after('lead_score');
            $table->text('smm_analysis')->nullable()->after('lead_category');
            $table->string('linkedin_url')->nullable()->after('smm_analysis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'industry',
                'website',
                'vk_url',
                'vk_status',
                'lead_score',
                'lead_category',
                'smm_analysis',
                'linkedin_url'
            ]);
        });
    }
};
