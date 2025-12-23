<?php

declare(strict_types=1);

namespace App\Filament\Resources\PeopleResource\Pages;

use App\Filament\Actions\FindWebsiteWithExaAction;
use App\Filament\Actions\EnrichWithExaAction;
use App\Filament\Actions\GenerateRecordSummaryAction;
use App\Filament\Resources\CompanyResource;
use App\Filament\Resources\PeopleResource;
use App\Jobs\PerformDeepAiAnalysis;
use App\Jobs\PerformSmmAnalysis;
use App\Models\People;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Relaticle\CustomFields\Facades\CustomFields;

final class ViewPeople extends ViewRecord
{
    protected static string $resource = PeopleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                \Filament\Actions\Action::make('Find VK Link')
                    ->icon('heroicon-m-magnifying-glass')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('query')
                            ->label('Search Query')
                            ->default(fn($record) => $record->name . ($record->company ? ' ' . $record->company->name : ''))
                            ->required(),
                    ])
                    ->action(function (People $record, array $data, \App\Services\VkActionService $vkService) {
                        $url = $vkService->findGroup($data['query']); // Works for users too if they have groups or just search
            
                        if ($url) {
                            $record->update(['vk_url' => $url]);
                            \Filament\Notifications\Notification::make()
                                ->title('VK Link Found')
                                ->body("Found and saved: $url")
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
                    ->action(function (People $record) {
                        if (!$record->vk_url) {
                            \Filament\Notifications\Notification::make()
                                ->title('No VK Link')
                                ->body('Please find a VK link first.')
                                ->danger()
                                ->send();
                            return;
                        }

                        PerformSmmAnalysis::dispatch($record);

                        \Filament\Notifications\Notification::make()
                            ->title('SMM Analysis Started')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('Deep AI Analysis')
                    ->icon('heroicon-m-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (People $record) {
                        if (!$record->vk_url) {
                            \Filament\Notifications\Notification::make()->title('No VK Link')->danger()->send();
                            return;
                        }

                        PerformDeepAiAnalysis::dispatch($record);

                        \Filament\Notifications\Notification::make()
                            ->title('AI Analysis Started')
                            ->info()
                            ->send();
                    }),
                FindWebsiteWithExaAction::make(),
                EnrichWithExaAction::make(),
                GenerateRecordSummaryAction::make(),
            ])
                ->label('Enrichment')
                ->icon('heroicon-m-sparkles')
                ->color('primary')
                ->button(),
            ActionGroup::make([
                EditAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Flex::make([
                    ImageEntry::make('avatar')
                        ->label('')
                        ->height(60)
                        ->circular()
                        ->grow(false),
                    Section::make()->schema([
                        TextEntry::make('name')
                            ->label('')
                            ->size(TextSize::Large)
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                        TextEntry::make('position')
                            ->label('')
                            ->size(TextSize::Medium)
                            ->color('gray'),
                        TextEntry::make('company.name')
                            ->label('')
                            ->color('primary')
                            ->url(fn(People $record): ?string => $record->company ? CompanyResource::getUrl('view', [$record->company]) : null),
                    ]),
                ]),
            ])->columnSpanFull(),

            Section::make('Verification Status')
                ->description('Email Validation & Mosint Intelligence')
                ->schema([
                    TextEntry::make('validation_status_label')
                        ->label('Email Status')
                        ->state(function (People $record) {
                            if (str_contains($record->notes ?? '', '[Mosint] ❌ INVALID')) {
                                return '❌ Invalid: No MX Records found';
                            }
                            if ($record->email) {
                                return '✅ Valid (MX Present)';
                            }
                            return 'No Email';
                        })
                        ->color(function (People $record) {
                            if (str_contains($record->notes ?? '', '[Mosint] ❌ INVALID')) {
                                return 'danger';
                            }
                            if ($record->email) {
                                return 'success';
                            }
                            return 'gray';
                        })
                        ->weight(\Filament\Support\Enums\FontWeight::Bold),

                    TextEntry::make('ip_organization')
                        ->label('IP Organization'),

                    TextEntry::make('twitter_url')
                        ->label('Twitter')
                        ->url(fn($state) => $state)
                        ->openUrlInNewTab()
                        ->color('primary'),

                    TextEntry::make('mosint_signals')
                        ->label('Mosint Signals')
                        ->state(function (People $record) {
                            $data = $record->osint_data;
                            if (!$data)
                                return null;

                            $signals = [];
                            // Handle associative or indexed
                            $twitter = $data['twitter'] ?? ($data[2] ?? false);
                            $spotify = $data['spotify'] ?? ($data[3] ?? false);

                            if ($twitter)
                                $signals[] = 'Twitter';
                            if ($spotify)
                                $signals[] = 'Spotify';

                            return $signals;
                        })
                        ->badge()
                        ->color('success'),

                    TextEntry::make('dns_summary')
                        ->label('DNS Анализ')
                        ->state(function (People $record) {
                            $data = $record->osint_data;
                            if (!$data)
                                return 'Нет данных';

                            $dns = $data['dns_records'] ?? ($data[5] ?? []);
                            if (empty($dns))
                                return 'DNS записи не найдены';

                            $mxRecords = [];
                            foreach ($dns as $rec) {
                                $type = $rec['Type'] ?? $rec[0] ?? '';
                                $val = $rec['Value'] ?? $rec[1] ?? '';
                                if ($type === 'MX') {
                                    $mxRecords[] = $val;
                                }
                            }

                            if (count($mxRecords) > 0) {
                                // Extract domain or host from first MX
                                $firstMx = $mxRecords[0];
                                // Usually format is "10 mx.google.com." - remove priority
                                $parts = explode(' ', trim($firstMx));
                                $host = end($parts);
                                $host = rtrim($host, '.'); // Remove trailing dot
                
                                return "✅ Почтовый домен активен, маршрутизация через {$host}";
                            }

                            return '❌ Почтовые серверы (MX) не найдены — прием почты невозможен.';
                        })
                        ->columnSpanFull()
                        ->color(fn($state) => str_contains($state, '✅') ? 'success' : 'danger'),
                ])->columns(3),

            Section::make('Contact Info')
                ->schema([
                    TextEntry::make('email')->icon('heroicon-m-envelope')->copyable(),
                    TextEntry::make('phone')->icon('heroicon-m-phone')->copyable(),
                    TextEntry::make('website')->url(fn($state) => $state)->openUrlInNewTab()->color('primary'),
                    TextEntry::make('linkedin_location')->label('Location'),
                ])->columns(2),

            Section::make('Social & Analysis')
                ->schema([
                    TextEntry::make('linkedin_url')
                        ->label('LinkedIn')
                        ->icon('heroicon-m-link')
                        ->url(fn($state) => $state)
                        ->openUrlInNewTab()
                        ->color('primary'),
                    TextEntry::make('vk_url')
                        ->label('VK')
                        ->icon('heroicon-m-link')
                        ->url(fn($state) => $state)
                        ->openUrlInNewTab()
                        ->color('primary'),
                    TextEntry::make('vk_status')->badge(),
                    TextEntry::make('lead_score')->label('Score'),
                    TextEntry::make('lead_category')->badge(),
                    TextEntry::make('smm_analysis')
                        ->columnSpanFull()
                        ->markdown(),
                ])->columns(3),

            Section::make('Custom Fields')->schema([
                CustomFields::infolist()->forSchema($schema)->build(),
            ])->columnSpanFull(),
        ]);
    }
}
