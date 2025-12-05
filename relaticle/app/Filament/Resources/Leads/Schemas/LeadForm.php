<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('company_name')
                            ->label('Компания')
                            ->maxLength(255),
                        TextInput::make('position')
                            ->label('Должность')
                            ->maxLength(255),
                        TextInput::make('linkedin_url')
                            ->label('LinkedIn')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('vk_url')
                            ->label('ВКонтакте')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('telegram_username')
                            ->label('Telegram')
                            ->maxLength(255)
                            ->prefix('@'),
                        Select::make('source')
                            ->label('Источник')
                            ->options([
                                'manual' => 'Ручной ввод',
                                'gigachat' => 'GigaChat',
                                'yandexgpt' => 'YandexGPT',
                                'perplexity' => 'Perplexity',
                            ])
                            ->default('manual'),
                    ]),
            ])
            ->columns(1)
            ->inlineLabel();
    }
}
