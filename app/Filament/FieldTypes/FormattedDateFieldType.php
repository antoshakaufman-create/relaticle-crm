<?php

declare(strict_types=1);

namespace App\Filament\FieldTypes;

use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Formatted Date field type

 */
class FormattedDateFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::date()
            ->key('formatted-date')
            ->label('Formatted Date')
            ->icon('heroicon-o-calendar')
            ->withSettings(
                FormattedDateSettings::class,
                fn() => [
                    \Filament\Forms\Components\TextInput::make('format')
                        ->label('Display Format')
                        ->placeholder('d.m.Y')
                        ->helperText('PHP Date Format (e.g. d.m.Y, Y-m-d)')
                        ->required(),
                ]
            )
            ->formComponent($this->getFormComponent())
            ->tableColumn($this->getTableColumn())
            ->infolistEntry($this->getInfolistEntry())
            ->priority(100)
            ->availableValidationRules([
                ValidationRule::REQUIRED,
            ])
            ->searchable()
            ->sortable();
    }

    private function getFormComponent(): Closure
    {
        return function (CustomField $customField) {
            $format = $customField->settings['format'] ?? 'd.m.Y';

            return DatePicker::make($customField->getFieldName())
                ->label($customField->name)
                ->displayFormat($format)
                ->columnSpanFull();
        };
    }

    private function getTableColumn(): Closure
    {
        return function (CustomField $customField) {
            return TextColumn::make($customField->getFieldName())
                ->label($customField->name)
                ->sortable()
                ->searchable();
        };
    }

    private function getInfolistEntry(): Closure
    {
        return function (CustomField $customField) {
            return TextEntry::make($customField->getFieldName())
                ->label($customField->name);
        };
    }
}