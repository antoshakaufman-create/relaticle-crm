<?php

declare(strict_types=1);

namespace App\Services\AI;

final class HybridAIService
{
    public function __construct()
    {
        // Используем только YandexGPT
    }

    /**
     * Анализ лида с использованием YandexGPT
     */
    public function analyzeLead(array $leadData, array $validationResults): ?AIAnalysis
    {
        // Используем только YandexGPT
        if (config('ai.yandex.api_key') && config('ai.yandex.folder_id')) {
            try {
                $yandexGPT = app(YandexGPTService::class);
                $yandexAnalysis = $yandexGPT->analyzeCredibility($leadData, $validationResults);
                
                return $yandexAnalysis;
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
        }

        return null;
    }

    /**
     * Поиск информации о компании через YandexGPT
     */
    public function searchCompanyInfo(string $companyName): ?array
    {
        if (config('ai.yandex.api_key') && config('ai.yandex.folder_id')) {
            try {
                $yandexGPT = app(YandexGPTService::class);
                $query = "Найди актуальную информацию о компании '{$companyName}' в России. Проверь её существование, ИНН, адрес, сайт, контакты. Используй российские источники: Контур.Компас, Rusprofile, Яндекс.Справочник, 2GIS.";
                $result = $yandexGPT->search($query);

                return $result;
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
        }

        return null;
    }
}

