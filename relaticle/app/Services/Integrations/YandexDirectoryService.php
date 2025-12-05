<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class YandexDirectoryService
{
    private ?string $apiKey;
    private string $baseUrl = 'https://search-maps.yandex.ru/v1';

    public function __construct()
    {
        $this->apiKey = config('ai.yandex_directory.api_key');
    }

    public function search(string $companyName, ?string $city = null): CompanySearchResult
    {
        if (!$this->apiKey) {
            return CompanySearchResult::notFound('API ключ не настроен');
        }

        try {
            $params = [
                'apikey' => $this->apiKey,
                'text' => $companyName,
                'lang' => 'ru_RU',
                'type' => 'biz',
            ];

            if ($city) {
                $params['ll'] = $this->getCityCoordinates($city);
            }

            $response = Http::timeout(10)->get($this->baseUrl, $params);

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['features'])) {
                    $place = $data['features'][0]; // Берем первый результат
                    $properties = $place['properties'] ?? [];

                    return CompanySearchResult::found([
                        'name' => $properties['name'] ?? $companyName,
                        'address' => $properties['description'] ?? null,
                        'phone' => $properties['Phones'] ?? null,
                        'website' => $properties['url'] ?? null,
                        'coordinates' => $place['geometry']['coordinates'] ?? null,
                    ]);
                }
            }

            return CompanySearchResult::notFound('Компания не найдена в Яндекс.Справочнике');
        } catch (\Exception $e) {
            Log::warning('YandexDirectory API error: '.$e->getMessage());

            return CompanySearchResult::notFound('Ошибка API: '.$e->getMessage());
        }
    }

    private function getCityCoordinates(string $city): ?string
    {
        // Простая карта координат для основных городов России
        $cities = [
            'Москва' => '37.6173,55.7558',
            'Санкт-Петербург' => '30.3159,59.9391',
            'Новосибирск' => '82.9346,55.0084',
            'Екатеринбург' => '60.6122,56.8431',
            'Казань' => '49.1056,55.8304',
            'Нижний Новгород' => '44.0020,56.2965',
        ];

        return $cities[$city] ?? null;
    }
}



