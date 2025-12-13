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
            $table->string('linkedin_url')->nullable()->after('vk_status');
            $table->string('linkedin_position')->nullable()->after('linkedin_url');
            $table->string('linkedin_company')->nullable()->after('linkedin_position');
            $table->string('linkedin_location')->nullable()->after('linkedin_company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['linkedin_url', 'linkedin_position', 'linkedin_company', 'linkedin_location']);
        });
    }
};
