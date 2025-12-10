<?php

declare(strict_types=1);

namespace App\Services\LeadValidation;

final class SocialMediaValidationService
{
    public function validateVK(string $vkUrl, string $name): ValidationResult
    {
        // Проверка формата URL ВКонтакте
        if (!$this->isValidVKUrl($vkUrl)) {
            return ValidationResult::invalid('Неверный формат URL ВКонтакте');
        }

        // Без AI - только проверка формата
        // AI проверка будет добавлена позже через главный сервис валидации
        return ValidationResult::neutral('Формат URL корректен', ['vk_url' => $vkUrl]);
    }

    public function validateTelegram(string $username, string $name): ValidationResult
    {
        // Проверка формата username Telegram
        if (!$this->isValidTelegramUsername($username)) {
            return ValidationResult::invalid('Неверный формат Telegram username');
        }

        // Без AI - только проверка формата
        // AI проверка будет добавлена позже через главный сервис валидации
        return ValidationResult::neutral('Формат username корректен', ['telegram' => $username]);
    }

    private function isValidVKUrl(string $url): bool
    {
        return (bool) preg_match('/^https?:\/\/(www\.)?(vk\.com|vkontakte\.ru)\/.+/i', $url);
    }

    private function isValidTelegramUsername(string $username): bool
    {
        // Убираем @ если есть
        $username = ltrim($username, '@');

        // Telegram username: 5-32 символа, буквы, цифры, подчеркивания
        return (bool) preg_match('/^[a-zA-Z0-9_]{5,32}$/', $username);
    }

    private function compareNames(string $name1, string $name2): bool
    {
        // Нормализуем имена для сравнения
        $normalize = fn (string $name): string => mb_strtolower(trim($name));

        $n1 = $normalize($name1);
        $n2 = $normalize($name2);

        // Точное совпадение
        if ($n1 === $n2) {
            return true;
        }

        // Проверка на частичное совпадение (имя или фамилия)
        $parts1 = explode(' ', $n1);
        $parts2 = explode(' ', $n2);

        foreach ($parts1 as $part1) {
            foreach ($parts2 as $part2) {
                if ($part1 === $part2 && strlen($part1) > 2) {
                    return true;
                }
            }
        }

        return false;
    }
}

