<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadValidationStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Services\LeadValidation\LeadValidationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->icon(fn ($record) => $record->email_verified ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->iconColor(fn ($record) => $record->email_verified ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->icon(fn ($record) => $record->phone_verified ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->iconColor(fn ($record) => $record->phone_verified ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label('Компания')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('validation_status')
                    ->label(__('resources.lead.validation_status'))
                    ->badge()
                    ->color(fn (LeadValidationStatus $state) => $state->getColor())
                    ->formatStateUsing(fn (LeadValidationStatus $state) => $state->getLabel())
                    ->sortable(),
                TextColumn::make('validation_score')
                    ->label(__('resources.lead.validation_score'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('source')
                    ->label(__('resources.lead.source'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('resources.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('validation_status')
                    ->label(__('resources.lead.validation_status'))
                    ->options(collect(LeadValidationStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
                SelectFilter::make('source')
                    ->label(__('resources.lead.source'))
                    ->options([
                        'manual' => 'Ручной ввод',
                        'gigachat' => 'GigaChat',
                        'yandexgpt' => 'YandexGPT',
                        'perplexity' => 'Perplexity',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('revalidate')
                    ->label(__('resources.lead.revalidate'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Lead $record) {
                        $validationService = App::make(LeadValidationService::class);
                        $leadData = [
                            'name' => $record->name,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'company_name' => $record->company_name,
                            'position' => $record->position,
                            'linkedin_url' => $record->linkedin_url,
                            'vk_url' => $record->vk_url,
                            'telegram_username' => $record->telegram_username,
                        ];

                        $result = $validationService->validateLead($leadData);

                        $record->update([
                            'validation_status' => $result->status->value,
                            'validation_score' => $result->score,
                            'validation_errors' => $result->getErrors(),
                            'email_verified' => isset($result->details['email']) && $result->details['email']->isValid(),
                            'phone_verified' => isset($result->details['phone']) && $result->details['phone']->isValid(),
                            'company_verified' => isset($result->details['company']) && $result->details['company']->isValid(),
                        ]);

                        Notification::make()
                            ->title('Валидация выполнена')
                            ->success()
                            ->send();
                    }),
                Action::make('convert_to_contact')
                    ->label(__('resources.lead.convert_to_contact'))
                    ->icon('heroicon-o-user')
                    ->action(function (Lead $record) {
                        $people = $record->convertToPeople();

                        Notification::make()
                            ->title('Лид конвертирован в контакт')
                            ->success()
                            ->send();

                        return redirect()->route('filament.app.resources.people.view', $people);
                    })
                    ->visible(fn (Lead $record) => !$record->people_id),
                Action::make('convert_to_company')
                    ->label(__('resources.lead.convert_to_company'))
                    ->icon('heroicon-o-building-office')
                    ->action(function (Lead $record) {
                        $company = $record->convertToCompany();

                        Notification::make()
                            ->title('Лид конвертирован в компанию')
                            ->success()
                            ->send();

                        return redirect()->route('filament.app.resources.companies.view', $company);
                    })
                    ->visible(fn (Lead $record) => !$record->company_id && $record->company_name),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
