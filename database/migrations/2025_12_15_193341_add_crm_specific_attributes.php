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
        // Companies: Financial & Engagement
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->integer('number_of_employees')->nullable();
            $table->integer('founded_year')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->dateTime('last_contacted_at')->nullable();
            $table->decimal('engagement_score', 5, 2)->nullable(); // 0-100

            // Marketing
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->integer('marketing_campaign_id')->nullable();
        });

        // People: Engagement & GDPR
        Schema::table('people', function (Blueprint $table) {
            $table->dateTime('last_contacted_at')->nullable();
            $table->dateTime('last_email_opened_at')->nullable();

            // GDPR
            $table->boolean('gdpr_consent_given')->default(false);
            $table->dateTime('gdpr_consent_date')->nullable();
            $table->boolean('email_opt_in')->default(false);

            // Marketing
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->integer('marketing_campaign_id')->nullable();
        });

        // Opportunities: Sales Pipeline
        Schema::table('opportunities', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->nullable();
            $table->integer('probability')->nullable(); // 0-100
            $table->date('close_date')->nullable();
            $table->text('next_step')->nullable();
            $table->string('competitor', 100)->nullable();
            $table->text('lost_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'annual_revenue',
                'number_of_employees',
                'founded_year',
                'currency_code',
                'last_contacted_at',
                'engagement_score',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'marketing_campaign_id'
            ]);
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'last_contacted_at',
                'last_email_opened_at',
                'gdpr_consent_given',
                'gdpr_consent_date',
                'email_opt_in',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'marketing_campaign_id'
            ]);
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn([
                'amount',
                'probability',
                'close_date',
                'next_step',
                'competitor',
                'lost_reason'
            ]);
        });
    }
};
