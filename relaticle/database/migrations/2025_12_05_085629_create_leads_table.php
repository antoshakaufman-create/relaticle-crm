<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('set null');
            $table->foreignId('people_id')->nullable()->constrained('people')->onDelete('set null');

            // Основные данные лида
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('position')->nullable();

            // Социальные сети
            $table->string('linkedin_url')->nullable();
            $table->string('vk_url')->nullable();
            $table->string('telegram_username')->nullable();

            // Источник и валидация
            $table->string('source')->default('manual'); // manual, gigachat, yandexgpt, perplexity
            $table->text('source_details')->nullable(); // Дополнительная информация об источнике
            $table->string('validation_status')->default('pending'); // pending, verified, suspicious, invalid, mock
            $table->integer('validation_score')->nullable(); // 0-100
            $table->json('validation_errors')->nullable(); // Ошибки валидации
            $table->json('enrichment_data')->nullable(); // Обогащенные данные (ИНН, адрес, сайт и т.д.)

            // Флаги проверки
            $table->boolean('email_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->boolean('company_verified')->default(false);
            $table->boolean('linkedin_verified')->default(false);
            $table->boolean('vk_verified')->default(false);
            $table->boolean('telegram_verified')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Индексы для быстрого поиска
            $table->index('email');
            $table->index('phone');
            $table->index('company_name');
            $table->index('validation_status');
            $table->index('source');
            $table->index('validation_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
