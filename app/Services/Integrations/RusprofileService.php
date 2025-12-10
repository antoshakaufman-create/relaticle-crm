<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Services\AI\GigaChatService;
use Illuminate\Support\Facades\Log;

final class RusprofileService
{
    public function __construct()
    {
        // GigaChat будет создан через app() при необходимости
    }

    public function search(string $companyName): CompanySearchResult
    {
        // Rusprofile может не иметь публичного API
        // Используем YandexGPT для поиска на Rusprofile
        try {
            $yandexGPT = app(\App\Services\AI\YandexGPTService::class);
            if (!$yandexGPT || !config('ai.yandex.api_key') || !config('ai.yandex.folder_id')) {
                return CompanySearchResult::notFound('YandexGPT сервис не настроен');
            }

            $query = "Найди компанию '{$companyName}' на rusprofile.ru. Верни ИНН, адрес, телефон, сайт если найдешь. Ответ должен быть в формате JSON.";

            $result = $yandexGPT->search($query);

            if ($result && !empty($result['data'])) {
                return CompanySearchResult::found($result['data']);
            }

            return CompanySearchResult::notFound('Компания не найдена на Rusprofile');
        } catch (\Exception $e) {
            Log::warning('Rusprofile search error: '.$e->getMessage());

            return CompanySearchResult::notFound('Ошибка поиска: '.$e->getMessage());
        }
    }
}

