<?php

declare(strict_types=1);

namespace App\Filament\FieldTypes;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Dropdown field type
 // withoutUserOptions() showcases built-in options - can be used with both single and multi choice
 */
class DropdownFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::singleChoice()
            ->key('dropdown')
            ->label('Dropdown')
            ->icon('heroicon-o-list-bullet')
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
            return Select::make($customField->getFieldName())
                ->label($customField->name)
                ->options($customField->options->pluck('name', 'id'))
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