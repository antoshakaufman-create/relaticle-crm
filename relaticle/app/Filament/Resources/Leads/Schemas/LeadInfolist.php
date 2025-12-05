<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadValidationStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Имя'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->icon(fn ($record) => $record->email_verified ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->iconColor(fn ($record) => $record->email_verified ? 'success' : 'gray'),
                        TextEntry::make('phone')
                            ->label('Телефон')
                            ->icon(fn ($record) => $record->phone_verified ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->iconColor(fn ($record) => $record->phone_verified ? 'success' : 'gray'),
                        TextEntry::make('company_name')
                            ->label('Компания'),
                        TextEntry::make('position')
                            ->label('Должность'),
                    ])
                    ->columns(2),
                Section::make('Социальные сети')
                    ->schema([
                        TextEntry::make('linkedin_url')
                            ->label('LinkedIn')
                            ->url()
                            ->openUrlInNewTab(),
                        TextEntry::make('vk_url')
                            ->label('ВКонтакте')
                            ->url()
                            ->openUrlInNewTab(),
                        TextEntry::make('telegram_username')
                            ->label('Telegram')
                            ->formatStateUsing(fn ($state) => $state ? "@{$state}" : null),
                    ])
                    ->columns(3),
                Section::make('Валидация')
                    ->schema([
                        TextEntry::make('validation_status')
                            ->label('Статус валидации')
                            ->badge()
                            ->formatStateUsing(fn (LeadValidationStatus $state) => $state->getLabel())
                            ->color(fn (LeadValidationStatus $state) => $state->getColor()),
                        TextEntry::make('validation_score')
                            ->label('Оценка валидации')
                            ->badge()
                            ->color(fn ($state) => match (true) {
                                $state >= 80 => 'success',
                                $state >= 50 => 'warning',
                                default => 'danger',
                            }),
                        TextEntry::make('source')
                            ->label('Источник')
                            ->badge(),
                        IconEntry::make('email_verified')
                            ->label('Email проверен')
                            ->boolean(),
                        IconEntry::make('phone_verified')
                            ->label('Телефон проверен')
                            ->boolean(),
                        IconEntry::make('company_verified')
                            ->label('Компания проверена')
                            ->boolean(),
                    ])
                    ->columns(3),
            ]);
    }
}
