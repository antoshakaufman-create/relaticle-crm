<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CreationSource;
use App\Models\Company;
use App\Filament\Exports\CompanyExporter;
use App\Filament\Resources\CompanyResource\Pages\ListCompanies;
use App\Filament\Resources\CompanyResource\Pages\ViewCompany;
use App\Filament\Resources\CompanyResource\RelationManagers\PeopleRelationManager;
use Filament\Schemas\Components\Section;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Relaticle\CustomFields\Facades\CustomFields;

final class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';

    public static function getNavigationLabel(): string
    {
        return __('resources.company.label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.company.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.company.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources.workspace.label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('account_owner_id')
                    ->relationship('accountOwner', 'name')
                    ->label(__('resources.company.account_owner'))
                    ->nullable()
                    ->preload()
                    ->searchable(),

                Section::make('Enrichment Data')
                    ->description('Automatic SMM Analysis & Lead Score')
                    ->schema([
                        TextInput::make('industry')
                            ->label('Отрасль'),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->suffixIcon('heroicon-m-globe-alt'),
                        TextInput::make('vk_url')
                            ->label('VK')
                            ->url()
                            ->suffixIcon('heroicon-m-link'),
                        Select::make('vk_status')
                            ->label('VK Status')
                            ->options([
                                'ACTIVE' => 'Active',
                                'INACTIVE' => 'Inactive',
                                'DEAD' => 'Dead',
                            ]),
                        TextInput::make('lead_score')
                            ->label('Lead Score')
                            ->numeric(),
                        TextInput::make('er_score')
                            ->label('ER %')
                            ->numeric(),
                        TextInput::make('posts_per_month')
                            ->label('Posts/Month')
                            ->numeric(),
                        Select::make('lead_category')
                            ->label('Category')
                            ->options([
                                'HOT' => 'HOT',
                                'WARM' => 'WARM',
                                'COLD-WARM' => 'COLD-WARM',
                                'COLD' => 'COLD',
                            ]),
                        \Filament\Forms\Components\Repeater::make('smm_analysis.related_links')
                            ->label('Ссылки связанные с брендом')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Название')
                                    ->required(),
                                TextInput::make('url')
                                    ->label('Ссылка')
                                    ->url()
                                    ->required()
                                    ->suffixIcon('heroicon-m-link'),
                            ])
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(fn(array $state): ?string => $state['title'] ?? null)
                            ->addActionLabel('Добавить ссылку')
                            ->columnSpanFull(),
                        \Filament\Forms\Components\Textarea::make('smm_analysis.summary')
                            ->label('SMM Анализ (Текст)')
                            ->rows(3)
                            ->columnSpanFull(),
                        \Filament\Forms\Components\Textarea::make('comment')
                            ->label('Комментарии')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->collapsible(),

                Section::make('Legal Details')
                    ->description('Official company information')
                    ->schema([
                        TextInput::make('legal_name')->label('Юр. Лицо')->columnSpanFull(),
                        TextInput::make('inn')->label('ИНН'),
                        TextInput::make('kpp')->label('КПП'),
                        TextInput::make('ogrn')->label('ОГРН'),
                        TextInput::make('management_name')->label('CEO Name'),
                        TextInput::make('management_post')->label('CEO Post'),
                        TextInput::make('status')->label('Legal Status'),
                        TextInput::make('okved')->label('OKVED'),
                        TextInput::make('address_line_1')->label('Legal Address')->columnSpanFull(),
                    ])->columns(2)->collapsible(),

                Section::make('Additional Information')
                    ->schema([
                        CustomFields::form()->forSchema($schema)->build()->columns(1),
                    ])
                    ->collapsible(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')->label('')->imageSize(28)->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('industry')
                    ->label('Отрасль')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('website')
                    ->label('Сайт')
                    ->url(fn($state) => $state)
                    ->openUrlInNewTab()
                    ->icon('heroicon-m-globe-alt')
                    ->toggleable(),
                TextColumn::make('vk_url')
                    ->label('VK')
                    ->url(fn($state) => $state)
                    ->openUrlInNewTab()
                    ->icon('heroicon-m-link')
                    ->toggleable(),
                TextColumn::make('lead_score')
                    ->label('Score')
                    ->numeric(1)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('er_score')
                    ->label('ER')
                    ->numeric(2)
                    ->sortable()
                    ->suffix('%'),
                TextColumn::make('posts_per_month')
                    ->label('Посты')
                    ->numeric(0)
                    ->sortable()
                    ->suffix('/мес'),
                TextColumn::make('smm_analysis.summary')
                    ->label('SMM')
                    ->limit(50)
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('lead_category')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'HOT' => 'danger',
                        'WARM' => 'success',
                        'COLD-WARM' => 'warning',
                        'COLD' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('annual_revenue')
                    ->label('Выручка')
                    ->money('RUB', divideBy: 1)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('company_size')
                    ->label('Размер')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'LARGE' => 'success',
                        'MEDIUM' => 'info',
                        'SMALL' => 'warning',
                        'MICRO' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'LARGE' => 'Крупная',
                        'MEDIUM' => 'Средняя',
                        'SMALL' => 'Малая',
                        'MICRO' => 'Микро',
                        default => '-',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vk_status')
                    ->badge()
                    ->color(fn(string $state): string => match (true) {
                        str_contains($state, 'ACTIVE') => 'success',
                        str_contains($state, 'INACTIVE') => 'warning',
                        default => 'danger',
                    })
                    ->toggleable(),
                TextColumn::make('comment')
                    ->label('Комментарии')
                    ->limit(50)
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('accountOwner.name')
                    ->label(__('resources.company.account_owner'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label(__('resources.common.created_by'))
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->getStateUsing(fn(Company $record): string => $record->created_by)
                    ->color(fn(Company $record): string => $record->isSystemCreated() ? 'secondary' : 'primary'),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('created_at')
                    ->label(__('resources.common.creation_date'))
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('resources.common.last_update'))
                    ->since()
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('creation_source')
                    ->label(__('resources.common.creation_source'))
                    ->options(CreationSource::class)
                    ->multiple(),
                SelectFilter::make('vk_status')
                    ->label('VK Статус')
                    ->options([
                        'ACTIVE' => 'Active',
                        'INACTIVE' => 'Inactive',
                        'DEAD' => 'Dead',
                    ])
                    ->multiple(),
                SelectFilter::make('lead_category')
                    ->label('Категория')
                    ->options([
                        'HOT' => 'HOT',
                        'WARM' => 'WARM',
                        'COLD-WARM' => 'COLD-WARM',
                        'COLD' => 'COLD',
                    ])
                    ->multiple(),
                SelectFilter::make('company_size')
                    ->label('Размер компании')
                    ->options([
                        'LARGE' => 'Крупная (2B+ ₽)',
                        'MEDIUM' => 'Средняя (800M-2B ₽)',
                        'SMALL' => 'Малая (120M-800M ₽)',
                        'MICRO' => 'Микро (<120M ₽)',
                    ])
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    RestoreAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(CompanyExporter::class),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PeopleRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'view' => ViewCompany::route('/{record}'),
            // 'edit' => \App\Filament\Resources\CompanyResource\Pages\EditCompany::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    /**
     * @return Builder<Company>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
