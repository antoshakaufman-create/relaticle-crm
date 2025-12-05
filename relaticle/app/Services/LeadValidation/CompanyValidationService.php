<?php

declare(strict_types=1);

namespace App\Services\LeadValidation;

use App\Services\Integrations\KonturCompassService;
use App\Services\Integrations\RusprofileService;
use App\Services\Integrations\TwoGisService;
use App\Services\Integrations\YandexDirectoryService;

final class CompanyValidationService
{
    /**
     * Паттерны для тестовых названий компаний
     */
    private const MOCK_PATTERNS = [
        '/тест/i',
        '/пример/i',
        '/образец/i',
        '/test\s+company/i',
        '/example\s+inc/i',
        '/sample\s+llc/i',
        '/ооо\s+тест/i',
    ];

    public function __construct()
    {
        // Сервисы будут созданы через app() для избежания циклических зависимостей
    }

    public function validate(string $companyName, ?string $inn = null): ValidationResult
    {
        // 1. Проверка на mock-названия
        if ($this->isMockCompany($companyName)) {
            return ValidationResult::mock('Тестовое название компании');
        }

        $results = [];

        // 2. Поиск через Контур.Компас (приоритетный источник)
        if (config('ai.kontur_compass.enabled') && $inn) {
            try {
                $konturCompass = app(KonturCompassService::class);
                $konturResult = $konturCompass->searchByINN($inn);
                if ($konturResult->isFound()) {
                    $results['kontur'] = ValidationResult::valid('Найдено в Контур.Компас', [
                        'inn' => $inn,
                        'data' => $konturResult->getData(),
                    ]);
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки API
            }
        }

        if (config('ai.kontur_compass.enabled')) {
            try {
                $konturCompass = app(KonturCompassService::class);
                $konturResult = $konturCompass->searchByName($companyName);
                if ($konturResult->isFound()) {
                    $results['kontur'] = ValidationResult::valid('Найдено в Контур.Компас', [
                        'data' => $konturResult->getData(),
                    ]);
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки API
            }
        }

        // 3. Поиск через Rusprofile
        try {
            $rusprofile = app(RusprofileService::class);
            $rusprofileResult = $rusprofile->search($companyName);
            if ($rusprofileResult->isFound()) {
                $results['rusprofile'] = ValidationResult::valid('Найдено в Rusprofile', [
                    'data' => $rusprofileResult->getData(),
                ]);
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки API
        }

        // 4. Поиск через Яндекс.Справочник
        if (config('ai.yandex_directory.enabled')) {
            try {
                $yandexDirectory = app(YandexDirectoryService::class);
                $yandexResult = $yandexDirectory->search($companyName);
                if ($yandexResult->isFound()) {
                    $results['yandex'] = ValidationResult::valid('Найдено в Яндекс.Справочник', [
                        'data' => $yandexResult->getData(),
                    ]);
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки API
            }
        }

        // 5. Поиск через 2GIS (для проверки адреса)
        if (config('ai.two_gis.enabled')) {
            try {
                $twoGis = app(TwoGisService::class);
                $twoGisResult = $twoGis->search($companyName);
                if ($twoGisResult->isFound()) {
                    $results['2gis'] = ValidationResult::valid('Найдено в 2GIS', [
                        'data' => $twoGisResult->getData(),
                    ]);
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки API
            }
        }

        // Если найдено хотя бы в одном источнике - валидно
        if (!empty($results)) {
            return ValidationResult::combined(...array_values($results));
        }

        // Если не найдено нигде - подозрительно, но не invalid (может быть новая компания)
        return ValidationResult::suspicious('Компания не найдена в российских источниках', 30);
    }

    private function isMockCompany(string $name): bool
    {
        foreach (self::MOCK_PATTERNS as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}

