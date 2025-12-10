<?php

declare(strict_types=1);

namespace App\Services\LeadValidation;

final class PhoneValidationService
{
    /**
     * Коды операторов России
     */
    private const OPERATOR_CODES = [
        '900', '901', '902', '903', '904', '905', '906', '907', '908', '909', // МТС
        '910', '911', '912', '913', '914', '915', '916', '917', '918', '919', // МТС
        '920', '921', '922', '923', '924', '925', '926', '927', '928', '929', // Мегафон
        '930', '931', '932', '933', '934', '935', '936', '937', '938', '939', // Мегафон
        '960', '961', '962', '963', '964', '965', '966', '967', '968', '969', // Билайн
        '980', '981', '982', '983', '984', '985', '986', '987', '988', '989', // Мегафон
        '990', '991', '992', '993', '994', '995', '996', '997', '998', '999', // Разные
    ];

    /**
     * Паттерны для тестовых номеров
     */
    private const MOCK_PATTERNS = [
        '/^\+7999/',  // Тестовые номера
        '/^\+7900/',  // Часто используются для тестов
        '/^\+7123/',  // Очевидные тестовые
    ];

    public function validate(string $phone): ValidationResult
    {
        // 1. Нормализация российского телефона
        $normalized = $this->normalizeRussianPhone($phone);

        // 2. Проверка формата (+7 или 8)
        if (!$this->isValidRussianFormat($normalized)) {
            return ValidationResult::invalid('Неверный формат российского телефона');
        }

        // 3. Проверка на mock-номера
        if ($this->isMockPhone($normalized)) {
            return ValidationResult::mock('Обнаружен тестовый номер');
        }

        // 4. Проверка кода оператора
        $operatorResult = $this->validateOperator($normalized);

        return $operatorResult;
    }

    private function normalizeRussianPhone(string $phone): string
    {
        // Убираем все кроме цифр
        $digits = preg_replace('/\D/', '', $phone);

        // Если начинается с 8, заменяем на +7
        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        // Если начинается с 7 и 11 цифр
        if (str_starts_with($digits, '7') && strlen($digits) === 11) {
            return '+7'.substr($digits, 1);
        }

        // Если уже в формате +7
        if (str_starts_with($phone, '+7') && strlen($digits) === 11) {
            return '+7'.substr($digits, 1);
        }

        return $phone;
    }

    private function isValidRussianFormat(string $phone): bool
    {
        // Формат: +7XXXXXXXXXX (11 цифр после +7)
        return (bool) preg_match('/^\+7\d{10}$/', $phone);
    }

    private function isMockPhone(string $phone): bool
    {
        foreach (self::MOCK_PATTERNS as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }

        return false;
    }

    private function validateOperator(string $phone): ValidationResult
    {
        $code = substr($phone, 2, 3);

        if (in_array($code, self::OPERATOR_CODES, true)) {
            return ValidationResult::valid('Валидный код оператора');
        }

        return ValidationResult::suspicious('Неизвестный код оператора', 40);
    }
}



