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
        // Fix Companies Table
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('lead_score', 10, 2)->nullable()->change();
            $table->decimal('er_score', 10, 2)->nullable()->change();
            $table->integer('posts_per_month')->nullable()->change();
            // JSON casting usually strictly done via code, but we can set TEXT/JSON type db-side if DB supports it
            // SQLite just sees TEXT
            $table->json('smm_analysis')->nullable()->change();
        });

        // Fix Tasks Table
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('order_column')->nullable()->change();
        });

        // Fix Opportunities Table
        Schema::table('opportunities', function (Blueprint $table) {
            $table->integer('order_column')->nullable()->change();
        });

        // Fix Leads Table
        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('validation_score', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Partial rollback - reverting to generic numeric if needed
        Schema::table('companies', function (Blueprint $table) {
            $table->float('lead_score')->change();
            $table->float('er_score')->change();
            $table->float('posts_per_month')->change();
        });
    }
};
