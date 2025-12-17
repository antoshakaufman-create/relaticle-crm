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
            $table->string('legal_name')->nullable()->after('name');
            $table->string('inn')->nullable()->after('legal_name');
            $table->string('ogrn')->nullable()->after('inn');
            $table->string('kpp')->nullable()->after('ogrn');
            $table->string('management_name')->nullable()->after('kpp');
            $table->string('management_post')->nullable()->after('management_name');
            $table->string('okved')->nullable()->after('management_post');
            $table->string('status')->nullable()->after('okved');
            $table->string('address_line_1')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'inn',
                'ogrn',
                'kpp',
                'management_name',
                'management_post',
                'okved',
                'status'
            ]);
        });
    }
};
