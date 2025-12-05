<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaskResource\Forms;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Relaticle\CustomFields\Facades\CustomFields;

final class TaskForm
{
    /**
     * @param  array<string>  $excludeFields
     *
     * @throws \Exception
     */
    public static function get(Schema $schema, array $excludeFields = []): Schema
    {
        $components = [
            TextInput::make('title')
                ->required()
                ->columnSpanFull(),
        ];

        if (! in_array('companies', $excludeFields)) {
            $components[] = Select::make('companies')
                ->label(__('resources.common.companies'))
                ->multiple()
                ->relationship('companies', 'name')
                ->columnSpanFull();
        }

        if (! in_array('people', $excludeFields)) {
            $components[] = Select::make('people')
                ->label(__('resources.common.people'))
                ->multiple()
                ->relationship('people', 'name')
                ->nullable();
        }

        $components[] = Select::make('assignees')
            ->label(__('resources.task.assignee'))
            ->multiple()
            ->relationship('assignees', 'name')
            ->nullable();

        $components[] = CustomFields::form()->forSchema($schema)->except($excludeFields)->build()->columnSpanFull();

        return $schema
            ->components($components)
            ->columns(2);
    }
}
