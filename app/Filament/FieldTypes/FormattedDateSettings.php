<?php

declare(strict_types=1);

namespace App\Filament\FieldTypes;

use Spatie\LaravelData\Data;

class FormattedDateSettings extends Data
{
    public function __construct(
        public ?string $format = 'd.m.Y',
    ) {
    }
}
