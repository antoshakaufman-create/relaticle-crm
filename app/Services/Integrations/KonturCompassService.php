<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class KonturCompassService
{
    private ?string $apiKey;
    private string $baseUrl = 'https://api.kontur.ru/compass/v1';

    public function __construct()
    {
        $this->apiKey = config('ai.kontur_compass.api_key');
    }

    public function searchByName(string $companyName): CompanySearchResult
    {
        if (!$this->apiKey) {
            return CompanySearchResult::notFound('API ключ не настроен');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                ])
                ->get("{$this->baseUrl}/companies/search", [
                    'query' => $companyName,
                    'region' => 'RU',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['items'])) {
                    $company = $data['items'][0]; // Берем первый результат

                    return CompanySearchResult::found([
                        'name' => $company['name'] ?? $companyName,
                        'inn' => $company['inn'] ?? null,
                        'address' => $company['address'] ?? null,
                        'website' => $company['website'] ?? null,
                        'phone' => $company['phone'] ?? null,
                        'email' => $company['email'] ?? null,
                    ]);
                }
            }

            return CompanySearchResult::notFound('Компания не найдена');
        } catch (\Exception $e) {
            Log::warning('KonturCompass API error: '.$e->getMessage());

            return CompanySearchResult::notFound('Ошибка API: '.$e->getMessage());
        }
    }

    public function searchByINN(string $inn): CompanySearchResult
    {
        if (!$this->apiKey) {
            return CompanySearchResult::notFound('API ключ не настроен');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                ])
                ->get("{$this->baseUrl}/companies/by-inn/{$inn}");

            if ($response->successful()) {
                $company = $response->json();

                if (!empty($company)) {
                    return CompanySearchResult::found([
                        'name' => $company['name'] ?? null,
                        'inn' => $inn,
                        'address' => $company['address'] ?? null,
                        'website' => $company['website'] ?? null,
                        'phone' => $company['phone'] ?? null,
                        'email' => $company['email'] ?? null,
                    ]);
                }
            }

            return CompanySearchResult::notFound('Компания с ИНН не найдена');
        } catch (\Exception $e) {
            Log::warning('KonturCompass API error: '.$e->getMessage());

            return CompanySearchResult::notFound('Ошибка API: '.$e->getMessage());
        }
    }
}



