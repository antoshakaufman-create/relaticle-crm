<?php

declare(strict_types=1);

namespace App\Services\LeadValidation;

final class EmailValidationService
{
    /**
     * Паттерны для определения mock email адресов
     */
    private const MOCK_PATTERNS = [
        '/test\d*@/i',
        '/example@/i',
        '/fake@/i',
        '/dummy@/i',
        '/sample@/i',
        '/noreply@/i',
        '/no-reply@/i',
        '/@test\./i',
        '/@example\./i',
        '/тест\d*@/i',
        '/пример@/i',
        '/info@test\./i',
        '/admin@test\./i',
    ];

    /**
     * Популярные российские домены
     */
    private const RUSSIAN_DOMAINS = [
        'mail.ru',
        'yandex.ru',
        'gmail.com',
        'rambler.ru',
        'bk.ru',
        'inbox.ru',
        'list.ru',
        'ya.ru',
    ];

    public function validate(string $email): ValidationResult
    {
        // 1. Базовая проверка формата
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::invalid('Неверный формат email');
        }

        // 2. Проверка на mock-адреса
        if ($this->isMockEmail($email)) {
            return ValidationResult::mock('Обнаружен тестовый email');
        }

        // 3. Проверка домена (российские домены)
        $domainResult = $this->validateRussianDomain($email);

        // 4. Проверка существования домена через DNS
        $dnsResult = $this->validateDomainDNS($email);

        // 5. Проверка MX-записей
        $mxResult = $this->validateMXRecords($email);

        // Комбинируем результаты
        return ValidationResult::combined($domainResult, $dnsResult, $mxResult);
    }

    private function isMockEmail(string $email): bool
    {
        foreach (self::MOCK_PATTERNS as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }

        return false;
    }

    private function validateRussianDomain(string $email): ValidationResult
    {
        $domain = substr(strrchr($email, '@'), 1);

        // Проверка популярных российских доменов
        if (in_array($domain, self::RUSSIAN_DOMAINS, true)) {
            return ValidationResult::valid('Популярный российский домен');
        }

        // Проверка на .ru, .рф домены
        if (preg_match('/\.(ru|рф)$/i', $domain)) {
            return ValidationResult::valid('Российский домен');
        }

        return ValidationResult::neutral('Международный домен');
    }

    private function validateDomainDNS(string $email): ValidationResult
    {
        $domain = substr(strrchr($email, '@'), 1);

        // Проверка существования домена
        if (!checkdnsrr($domain, 'A')) {
            return ValidationResult::invalid('Домен не существует');
        }

        return ValidationResult::valid('Домен существует');
    }

    private function validateMXRecords(string $email): ValidationResult
    {
        $domain = substr(strrchr($email, '@'), 1);

        // Проверка MX-записей (почтовый сервер)
        if (!checkdnsrr($domain, 'MX')) {
            return ValidationResult::suspicious('Нет MX-записей (почта может не работать)', 60);
        }

        return ValidationResult::valid('MX-записи найдены');
    }
}



