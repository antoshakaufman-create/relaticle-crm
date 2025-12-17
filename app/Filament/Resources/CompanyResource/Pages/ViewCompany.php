<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Enums\CustomFields\CompanyField;
use App\Filament\Actions\GenerateRecordSummaryAction;
use App\Filament\Components\Infolists\AvatarName;
use App\Filament\Resources\CompanyResource;
use App\Filament\Resources\CompanyResource\RelationManagers\NotesRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\PeopleRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\TasksRelationManager;
use App\Jobs\FetchFaviconForCompany;
use App\Jobs\PerformDeepAiAnalysis;
use App\Jobs\PerformSmmAnalysis;
use App\Models\Company;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Relaticle\CustomFields\Facades\CustomFields;

final class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('Find VK Link')
                ->icon('heroicon-m-magnifying-glass')
                ->form([
                    \Filament\Forms\Components\TextInput::make('query')
                        ->label('Search Query')
                        ->default(fn($record) => $record->name)
                        ->required(),
                ])
                ->action(function (Company $record, array $data, \App\Services\VkActionService $vkService) {
                    // 1. Try to extract domain from website
                    $domain = null;
                    if ($record->website) {
                        $host = parse_url($record->website, PHP_URL_HOST);
                        $domain = $host ? str_ireplace('www.', '', $host) : null;
                    }

                    // 2. If no website, try from employees
                    if (!$domain) {
                        $genericDomains = ['gmail.com', 'mail.ru', 'yandex.ru', 'bk.ru', 'list.ru', 'inbox.ru', 'outlook.com', 'hotmail.com', 'icloud.com'];
                        foreach ($record->people as $person) {
                            if ($person->email && str_contains($person->email, '@')) {
                                $parts = explode('@', $person->email);
                                $d = end($parts);
                                if (!in_array(strtolower($d), $genericDomains)) {
                                    $domain = $d;
                                    break; // Found corporate domain
                                }
                            }
                        }
                    }

                    $url = $vkService->findGroup($data['query'], $domain, $record->legal_name, $record->address_line_1);

                    if ($url) {
                        $record->update(['vk_url' => $url]);
                        $msg = "Found and saved: $url";
                        if ($domain)
                            $msg .= " (Verified via $domain)";

                        \Filament\Notifications\Notification::make()
                            ->title('VK Link Found')
                            ->body($msg)
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Not Found')
                            ->warning()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('SMM Analysis')
                ->icon('heroicon-m-chart-bar')
                ->requiresConfirmation()
                ->action(function (Company $record) {
                    if (!$record->vk_url) {
                        \Filament\Notifications\Notification::make()
                            ->title('No VK Link')
                            ->body('Please find a VK link first.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Dispatch Async Job
                    PerformSmmAnalysis::dispatch($record);

                    \Filament\Notifications\Notification::make()
                        ->title('SMM Analysis Started')
                        ->body('The analysis is running in the background. You will be notified when complete.')
                        ->success()
                        ->send();
                }),
            // Deep AI Analysis Action
            \Filament\Actions\Action::make('Deep AI Analysis')
                ->icon('heroicon-m-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (Company $record) {
                    if (!$record->vk_url) {
                        \Filament\Notifications\Notification::make()->title('No VK Link')->danger()->send();
                        return;
                    }

                    // Dispatch Async Job
                    PerformDeepAiAnalysis::dispatch($record);

                    \Filament\Notifications\Notification::make()
                        ->title('AI Analysis Started')
                        ->body('This process may take several minutes. You will be notified when complete.')
                        ->info()
                        ->send();
                }),
            GenerateRecordSummaryAction::make(),
            ActionGroup::make([
                EditAction::make()
                    ->after(function (Company $record, array $data): void {
                        $this->dispatchFaviconFetchIfNeeded($record, $data);
                    }),
                DeleteAction::make(),
            ]),
        ];
    }

    /**
     * Dispatch favicon fetch job if domain_name custom field has changed.
     *
     * @param  array<string, mixed>  $data
     */
    private function dispatchFaviconFetchIfNeeded(Company $company, array $data): void
    {
        $customFieldsData = $data['custom_fields'] ?? [];
        $newDomain = $customFieldsData['domain_name'] ?? null;

        // Get the old domain value from the database
        $domainField = $company->customFields()
            ->whereBelongsTo($company->team)
            ->where('code', CompanyField::DOMAIN_NAME->value)
            ->first();

        $oldDomain = $domainField !== null ? $company->getCustomFieldValue($domainField) : null;

        // Only dispatch if domain changed and new value is not empty
        if (!in_array($newDomain, [$oldDomain, null, '', '0'], true)) {
            FetchFaviconForCompany::dispatch($company)->afterCommit();
        }
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Flex::make([
                    Section::make([
                        Flex::make([
                            AvatarName::make('logo')
                                ->avatar('logo')
                                ->name('name')
                                ->avatarSize('lg')
                                ->textSize('xl')
                                ->square()
                                ->label(''),
                            AvatarName::make('creator')
                                ->avatar('creator.avatar')
                                ->name('creator.name')
                                ->avatarSize('sm')
                                ->textSize('sm')  // Default text size for creator
                                ->circular()
                                ->label(__('resources.common.created_by')),
                            AvatarName::make('accountOwner')
                                ->avatar('accountOwner.avatar')
                                ->name('accountOwner.name')
                                ->avatarSize('sm')
                                ->textSize('sm')  // Default text size for account owner
                                ->circular()
                                ->label(__('resources.company.account_owner')),
                        ]),
                        CustomFields::infolist()->forSchema($schema)->build(),
                    ]),
                    Section::make([
                        TextEntry::make('created_at')
                            ->label(__('resources.common.creation_date'))
                            ->icon('heroicon-o-clock')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('resources.common.last_update'))
                            ->icon('heroicon-o-clock')
                            ->dateTime(),
                    ])->grow(false),
                ])->columnSpan('full'),

                Section::make('Legal Details')
                    ->schema([
                        Flex::make([
                            TextEntry::make('legal_name')->label('Юр. Лицо'),
                            TextEntry::make('inn')->label('ИНН'),
                            TextEntry::make('kpp')->label('КПП'),
                            TextEntry::make('ogrn')->label('ОГРН'),
                        ]),
                        Flex::make([
                            TextEntry::make('management_name')->label('CEO Name'),
                            TextEntry::make('management_post')->label('CEO Post'),
                        ]),
                        Flex::make([
                            TextEntry::make('status')->label('Status')->badge()->color(fn(string $state): string => match ($state) {
                                'ACTIVE' => 'success',
                                'LIQUIDATING' => 'warning',
                                'LIQUIDATED' => 'danger',
                                default => 'gray',
                            }),
                            TextEntry::make('okved')->label('OKVED'),
                        ]),
                        TextEntry::make('address_line_1')->label('Legal Address')->columnSpanFull(),
                    ])->collapsible(),

                Section::make('Social Metrics')
                    ->schema([
                        Flex::make([
                            TextEntry::make('vk_url')
                                ->label('VK')
                                ->icon('heroicon-m-link')
                                ->url(fn($state) => $state)
                                ->openUrlInNewTab()
                                ->color('primary'),
                            TextEntry::make('vk_status')
                                ->badge()
                                ->color(fn(string $state): string => match (true) {
                                    str_contains($state, 'ACTIVE') => 'success',
                                    str_contains($state, 'INACTIVE') => 'warning',
                                    default => 'danger',
                                }),
                        ]),
                        Flex::make([
                            TextEntry::make('er_score')->label('ER Score')->numeric(2),
                            TextEntry::make('posts_per_month')->label('Posts/Month')->numeric(1),
                            TextEntry::make('lead_score')->label('Lead Score'),
                            TextEntry::make('lead_category')->badge(),
                        ]),
                        \Filament\Infolists\Components\RepeatableEntry::make('smm_analysis.related_links')
                            ->label('Ссылки связанные с брендом')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('title')
                                    ->label('Название')
                                    ->icon('heroicon-m-link'),
                                \Filament\Infolists\Components\TextEntry::make('url')
                                    ->label('Ссылка')
                                    ->url(fn($state) => $state)
                                    ->openUrlInNewTab()
                                    ->color('primary'),
                            ])
                            ->grid(2)
                            ->columnSpanFull(),
                        TextEntry::make('smm_analysis.summary')
                            ->label('SMM Summary')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            PeopleRelationManager::class,
            TasksRelationManager::class,
            NotesRelationManager::class,
        ];
    }
}
