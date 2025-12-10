<?php

declare(strict_types=1);

namespace App\Services\LeadGeneration;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class HybridLeadService
{
    public function __construct()
    {
        // Сервисы будут созданы через app() при необходимости
    }

    /**
     * Поиск лидов через YandexGPT
     */
    public function searchLeads(string $query, array $filters = []): Collection
    {
        $leads = collect();

        // Используем только YandexGPT
        if (config('ai.yandex.api_key') && config('ai.yandex.folder_id')) {
            try {
                $yandexGPT = app(YandexGPTLeadService::class);
                $yandexLeads = $yandexGPT->searchLeads($query, $filters);
                $leads = $leads->merge($yandexLeads);
            } catch (\Exception $e) {
                Log::warning('YandexGPT search failed: '.$e->getMessage());
            }
        }

        // Удаляем дубликаты (по email или телефону)
        return $leads->unique(function ($lead) {
            return $lead->email ?? $lead->phone ?? $lead->id;
        });
    }
}

