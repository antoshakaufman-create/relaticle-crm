<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TwoGisService
{
    private ?string $apiKey;
    private string $baseUrl = 'https://catalog.api.2gis.com/3.0/items';

    public function __construct()
    {
        $this->apiKey = config('ai.two_gis.api_key');
    }

    public function search(string $companyName, ?string $city = null): CompanySearchResult
    {
        if (!$this->apiKey) {
            return CompanySearchResult::notFound('API ключ не настроен');
        }

        try {
            $params = [
                'key' => $this->apiKey,
                'q' => $companyName,
                'fields' => 'items.point,items.name,items.address_name,items.contacts',
            ];

            if ($city) {
                $regionId = $this->getCityRegionId($city);
                if ($regionId) {
                    $params['region_id'] = $regionId;
                }
            }

            $response = Http::timeout(10)->get($this->baseUrl, $params);

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['result']['items'])) {
                    $item = $data['result']['items'][0]; // Берем первый результат

                    return CompanySearchResult::found([
                        'name' => $item['name'] ?? $companyName,
                        'address' => $item['address_name'] ?? null,
                        'phone' => $item['contacts']['phones'][0]['formatted'] ?? null,
                        'website' => $item['contacts']['www'][0] ?? null,
                        'coordinates' => $item['point'] ?? null,
                    ]);
                }
            }

            return CompanySearchResult::notFound('Компания не найдена в 2GIS');
        } catch (\Exception $e) {
            Log::warning('2GIS API error: '.$e->getMessage());

            return CompanySearchResult::notFound('Ошибка API: '.$e->getMessage());
        }
    }

    private function getCityRegionId(string $city): ?int
    {
        // Простая карта region_id для основных городов России
        $cities = [
            'Москва' => 1,
            'Санкт-Петербург' => 2,
            'Новосибирск' => 4,
            'Екатеринбург' => 3,
            'Казань' => 43,
            'Нижний Новгород' => 47,
        ];

        return $cities[$city] ?? null;
    }
}



