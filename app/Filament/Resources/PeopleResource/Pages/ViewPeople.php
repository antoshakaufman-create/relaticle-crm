<?php

declare(strict_types=1);

namespace App\Filament\Resources\PeopleResource\Pages;

use App\Filament\Actions\GenerateRecordSummaryAction;
use App\Filament\Resources\CompanyResource;
use App\Filament\Resources\PeopleResource;
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
            GenerateRecordSummaryAction::make(),
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
                    \Filament\Schemas\Components\Section::make()->schema([
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

                    TextEntry::make('osint_data')
                        ->label('Raw OSINT Data')
                        ->formatStateUsing(fn($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->markdown()
                        ->prose()
                        ->columnSpanFull(),
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
