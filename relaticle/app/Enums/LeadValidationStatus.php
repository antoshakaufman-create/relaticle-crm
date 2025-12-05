<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeadValidationStatus: string implements HasLabel, HasColor
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case SUSPICIOUS = 'suspicious';
    case INVALID = 'invalid';
    case MOCK = 'mock';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает проверки',
            self::VERIFIED => 'Проверен',
            self::SUSPICIOUS => 'Подозрительный',
            self::INVALID => 'Невалидный',
            self::MOCK => 'Вымышленный',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::VERIFIED => 'success',
            self::SUSPICIOUS => 'warning',
            self::INVALID => 'danger',
            self::MOCK => 'danger',
        };
    }
}



