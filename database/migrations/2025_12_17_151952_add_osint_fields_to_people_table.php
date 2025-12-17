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
            $table->json('osint_data')->nullable()->comment('Raw OSINT findings (Mosint, Holehe)');
            $table->string('twitter_url')->nullable();
            $table->string('ip_organization')->nullable()->comment('Organization from IP Geolocation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['osint_data', 'twitter_url', 'ip_organization']);
        });
    }
};
