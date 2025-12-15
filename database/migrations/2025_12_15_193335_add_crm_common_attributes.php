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
        $columns = function (Blueprint $table) {
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('timezone', 50)->nullable();
        };

        Schema::table('companies', $columns);
        Schema::table('people', $columns);
        Schema::table('leads', $columns);
    }

    public function down(): void
    {
        $dropColumns = function (Blueprint $table) {
            $table->dropColumn([
                'address_line_1',
                'address_line_2',
                'city',
                'state_province',
                'postal_code',
                'country_code',
                'latitude',
                'longitude',
                'timezone'
            ]);
        };

        Schema::table('companies', $dropColumns);
        Schema::table('people', $dropColumns);
        Schema::table('leads', $dropColumns);
    }
};
