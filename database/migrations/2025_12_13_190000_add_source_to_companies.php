<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('companies', 'creation_source')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('creation_source')->nullable()->default('MANUAL');
            });
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('creation_source');
        });
    }
};
