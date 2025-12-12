<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('vk_url')->nullable()->after('website');
            $table->string('telegram_url')->nullable()->after('vk_url');
            $table->string('instagram_url')->nullable()->after('telegram_url');
            $table->string('youtube_url')->nullable()->after('instagram_url');
            $table->text('smm_analysis')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn(['vk_url', 'telegram_url', 'instagram_url', 'youtube_url', 'smm_analysis']);
        });
    }
};
