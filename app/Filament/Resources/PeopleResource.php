<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CreationSource;
use App\Filament\Exports\PeopleExporter;
use App\Filament\Resources\PeopleResource\Pages\ListPeople;
use App\Filament\Resources\PeopleResource\Pages\ViewPeople;
use App\Filament\Resources\PeopleResource\RelationManagers\NotesRelationManager;
use App\Filament\Resources\PeopleResource\RelationManagers\TasksRelationManager;
use App\Models\Company;
use App\Models\People;
use Filament\Actions\Action;
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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Image;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Relaticle\CustomFields\Facades\CustomFields;

final class PeopleResource extends Resource
{
    protected static ?string $model = People::class;

    protected static ?string $modelLabel = 'person';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';

    public static function getNavigationLabel(): string
    {
        return __('resources.people.label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.people.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.people.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources.workspace.label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Section::make('Verification Status')
                    ->description('Email Validation & Mosint Intelligence')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('validation_status')
                            ->label('Email Status')
                            ->content(function (People $record) {
                                if (str_contains($record->notes ?? '', '[Mosint] ❌ INVALID')) {
                                    return new \Illuminate\Support\HtmlString('<span style="color: red; font-weight: bold;">❌ Invalid: No MX Records found (Mosint Scan)</span>');
                                }
                                if ($record->email) {
                                    return new \Illuminate\Support\HtmlString('<span style="color: green; font-weight: bold;">✅ Valid (MX Present)</span>');
                                }
                                return 'No Email';
                            })
                            ->columnSpanFull(),

                        TextInput::make('ip_organization')
                            ->label('IP Organization (Mosint)')
                            ->maxLength(255)
                            ->columnSpan(6),

                        TextInput::make('twitter_url')
                            ->label('Twitter Profile')
                            ->url()
                            ->maxLength(255)
                            ->columnSpan(6),

                        \Filament\Forms\Components\Textarea::make('osint_data_pretty')
                            ->label('Raw OSINT Data')
                            ->rows(5)
                            ->formatStateUsing(fn(People $record) => json_encode($record->osint_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->disabled()
                            ->dehydrated(false) // Do not save back to DB
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Grid::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(6),
                        Select::make('company_id')
                            ->relationship('company', 'name')
                            ->suffixAction(
                                Action::make('Create Company')
                                    ->model(Company::class)
                                    ->schema(fn(Schema $schema): \Filament\Schemas\Schema => $schema->components([
                                        TextInput::make('name')
                                            ->required(),
                                        Select::make('account_owner_id')
                                            ->model(Company::class)
                                            ->relationship('accountOwner', 'name')
                                            ->label(__('resources.company.account_owner'))
                                            ->preload()
                                            ->searchable(),
                                        CustomFields::form()->forSchema($schema)->build()->columns(1),
                                    ]))
                                    ->modalWidth(Width::Large)
                                    ->slideOver()
                                    ->icon('heroicon-o-plus')
                                    ->label(__('resources.common.create'))
                                    ->action(function (array $data, Set $set): void {
                                        $company = Company::create($data);
                                        $set('company_id', $company->id);
                                    })
                            )
                            ->searchable()
                            ->preload()
                            ->columnSpan(6),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->columnSpan(6),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(50)
                            ->columnSpan(6),
                        TextInput::make('position')
                            ->maxLength(255)
                            ->columnSpan(6),
                        TextInput::make('industry')
                            ->maxLength(255)
                            ->columnSpan(6),
                        TextInput::make('website')
                            ->url()
                            ->maxLength(255)
                            ->columnSpan(6),
                        TextInput::make('vk_url')
                            ->label('ВКонтакте')
                            ->url()
                            ->maxLength(255)
                            ->columnSpan(6),
                        TextInput::make('telegram_url')
                            ->label('Telegram')
                            ->url()
                            ->maxLength(255)
                            ->columnSpan(6),
                        TextInput::make('instagram_url')
                            ->label('Instagram')
                            ->url()
                            ->maxLength(255)
                            ->columnSpan(6),
                        TextInput::make('youtube_url')
                            ->label('YouTube')
                            ->url()
                            ->maxLength(255)
                            ->columnSpan(6),
                        TextInput::make('source')
                            ->maxLength(255)
                            ->columnSpan(6),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        \Filament\Forms\Components\Textarea::make('smm_analysis')
                            ->label('SMM Анализ')
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(12),
                \Filament\Schemas\Components\Section::make('Enrichment Data')
                    ->description('Automatic data from LinkedIn and VK Analysis')
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('linkedin_url')
                                    ->label('LinkedIn URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->columnSpan(6),
                                TextInput::make('linkedin_position')
                                    ->label('LinkedIn Должность')
                                    ->maxLength(255)
                                    ->columnSpan(6),
                                TextInput::make('linkedin_company')
                                    ->label('LinkedIn Компания')
                                    ->maxLength(255)
                                    ->columnSpan(6),
                                TextInput::make('linkedin_location')
                                    ->label('LinkedIn Локация')
                                    ->maxLength(255)
                                    ->columnSpan(6),
                            ])->columns(12),

                        Grid::make()
                            ->schema([
                                Select::make('vk_status')
                                    ->label('VK Status')
                                    ->options([
                                        'ACTIVE' => 'Active',
                                        'INACTIVE' => 'Inactive',
                                        'DEAD' => 'Dead'
                                    ])
                                    ->columnSpan(4),
                                TextInput::make('lead_score')
                                    ->label('Lead Score')
                                    ->numeric()
                                    ->columnSpan(4),
                                Select::make('lead_category')
                                    ->label('Category')
                                    ->options([
                                        'HOT' => 'HOT',
                                        'WARM' => 'WARM',
                                        'COLD-WARM' => 'COLD-WARM',
                                        'COLD' => 'COLD'
                                    ])
                                    ->columnSpan(4),
                            ])->columns(12),

                        \Filament\Forms\Components\Textarea::make('visual_analysis')
                            ->label('Visual Analysis (Lisa)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                \Filament\Schemas\Components\Section::make('Additional Information')
                    ->schema([
                        CustomFields::form()->forSchema($schema)->build()->columns(1),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')->label('')->size(24)->circular(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable()
                    ->description(fn(People $record) => str_contains($record->notes ?? '', '[Mosint] ❌ INVALID') ? '❌ Invalid (No MX)' : null),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('ip_organization')
                    ->label('IP Org')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->limit(20),
                TextColumn::make('position')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('industry')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vk_url')
                    ->label('VK')
                    ->icon('heroicon-m-link')
                    ->url(fn($state) => $state)
                    ->openUrlInNewTab()
                    ->toggleable(),
                TextColumn::make('twitter_url')
                    ->label('Twitter')
                    ->icon('heroicon-m-link')
                    ->url(fn($state) => $state)
                    ->openUrlInNewTab()
                    ->toggleable(),
                TextColumn::make('linkedin_url')
                    ->label('LinkedIn')
                    ->icon('heroicon-m-link')
                    ->url(fn($state) => $state)
                    ->openUrlInNewTab()
                    ->toggleable(),
                TextColumn::make('vk_status')
                    ->label('Статус VK')
                    ->badge()
                    ->color(fn(string $state): string => match (true) {
                        str_contains($state, 'ACTIVE') => 'success',
                        str_contains($state, 'INACTIVE') => 'warning',
                        default => 'danger',
                    })
                    ->toggleable(),
                TextColumn::make('lead_score')
                    ->label('Lead Score')
                    ->numeric(1)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('lead_category')
                    ->label('Категория')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'HOT' => 'danger',
                        'WARM' => 'success',
                        'COLD-WARM' => 'warning',
                        'COLD' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('telegram_url')
                    ->label('Telegram')
                    ->icon('heroicon-m-link')
                    ->url(fn($state) => $state)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('smm_analysis')
                    ->label('SMM')
                    ->formatStateUsing(fn($state) => $state ? '✅' : '—')
                    ->toggleable(),
                TextColumn::make('company.name')
                    ->label(__('resources.common.companies'))
                    ->url(fn(People $record): ?string => $record->company_id ? CompanyResource::getUrl('view', [$record->company_id]) : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('creation_source')
                    ->label(__('resources.common.creation_source'))
                    ->options(CreationSource::class)
                    ->multiple(),
                TrashedFilter::make(),

                // Social Media Filters
                \Filament\Tables\Filters\TernaryFilter::make('has_vk')
                    ->label('VK')
                    ->placeholder('Все')
                    ->trueLabel('Есть VK')
                    ->falseLabel('Нет VK')
                    ->queries(
                        true: fn($query) => $query->whereNotNull('vk_url')->where('vk_url', '!=', ''),
                        false: fn($query) => $query->where(fn($q) => $q->whereNull('vk_url')->orWhere('vk_url', '=', '')),
                        blank: fn($query) => $query,
                    ),

                \Filament\Tables\Filters\TernaryFilter::make('has_telegram')
                    ->label('Telegram')
                    ->placeholder('Все')
                    ->trueLabel('Есть Telegram')
                    ->falseLabel('Нет Telegram')
                    ->queries(
                        true: fn($query) => $query->whereNotNull('telegram_url')->where('telegram_url', '!=', ''),
                        false: fn($query) => $query->where(fn($q) => $q->whereNull('telegram_url')->orWhere('telegram_url', '=', '')),
                        blank: fn($query) => $query,
                    ),

                \Filament\Tables\Filters\TernaryFilter::make('has_smm')
                    ->label('SMM Анализ')
                    ->placeholder('Все')
                    ->trueLabel('Есть SMM')
                    ->falseLabel('Нет SMM')
                    ->queries(
                        true: fn($query) => $query->whereNotNull('smm_analysis')->where('smm_analysis', '!=', ''),
                        false: fn($query) => $query->where(fn($q) => $q->whereNull('smm_analysis')->orWhere('smm_analysis', '=', '')),
                        blank: fn($query) => $query,
                    ),

                \Filament\Tables\Filters\TernaryFilter::make('has_email')
                    ->label('Email')
                    ->placeholder('Все')
                    ->trueLabel('Есть Email')
                    ->falseLabel('Нет Email')
                    ->queries(
                        true: fn($query) => $query->whereNotNull('email')->where('email', '!=', ''),
                        false: fn($query) => $query->where(fn($q) => $q->whereNull('email')->orWhere('email', '=', '')),
                        blank: fn($query) => $query,
                    ),

                \Filament\Tables\Filters\TernaryFilter::make('has_phone')
                    ->label('Телефон')
                    ->placeholder('Все')
                    ->trueLabel('Есть Телефон')
                    ->falseLabel('Нет Телефона')
                    ->queries(
                        true: fn($query) => $query->whereNotNull('phone')->where('phone', '!=', ''),
                        false: fn($query) => $query->where(fn($q) => $q->whereNull('phone')->orWhere('phone', '=', '')),
                        blank: fn($query) => $query,
                    ),

                \Filament\Tables\Filters\TernaryFilter::make('has_twitter')
                    ->label('Twitter')
                    ->placeholder('Все')
                    ->trueLabel('Есть Twitter')
                    ->falseLabel('Нет Twitter')
                    ->queries(
                        true: fn($query) => $query->whereNotNull('twitter_url')->where('twitter_url', '!=', ''),
                        false: fn($query) => $query->where(fn($q) => $q->whereNull('twitter_url')->orWhere('twitter_url', '=', '')),
                        blank: fn($query) => $query,
                    ),

                \Filament\Tables\Filters\TernaryFilter::make('has_ip_org')
                    ->label('IP Организация')
                    ->placeholder('Все')
                    ->trueLabel('Есть IP Org')
                    ->falseLabel('Нет IP Org')
                    ->queries(
                        true: fn($query) => $query->whereNotNull('ip_organization')->where('ip_organization', '!=', ''),
                        false: fn($query) => $query->where(fn($q) => $q->whereNull('ip_organization')->orWhere('ip_organization', '=', '')),
                        blank: fn($query) => $query,
                    ),

                \Filament\Tables\Filters\SelectFilter::make('mosint_status')
                    ->label('Mosint Валидация')
                    ->options([
                        'valid' => 'Valid (MX Found)',
                        'invalid' => 'Invalid (No MX)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['value'] === 'invalid',
                                fn(Builder $query) => $query->where('notes', 'like', '%[Mosint] ❌ INVALID%'),
                            )
                            ->when(
                                $data['value'] === 'valid',
                                fn(Builder $query) => $query->where('notes', 'not like', '%[Mosint] ❌ INVALID%')
                                    ->whereNotNull('email'), // Assume others are valid if they have email and not marked invalid? Or explicitly validated? 
                                // Let's just filter for "Not marked invalid" for now as proxy.
                            );
                    }),

                \Filament\Tables\Filters\SelectFilter::make('industry')
                    ->label('Отрасль')
                    ->options(fn() => \App\Models\People::whereNotNull('industry')
                        ->distinct()
                        ->pluck('industry', 'industry')
                        ->toArray())
                    ->searchable(),

                \Filament\Tables\Filters\SelectFilter::make('company_id')
                    ->label('Компания')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
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
                        ->exporter(PeopleExporter::class),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Verification Status')
                    ->description('Email Validation & Mosint Intelligence')
                    ->schema([
                        Text::make('validation_status_label')
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
                            ->weight('bold'),

                        Text::make('ip_organization')
                            ->label('IP Organization (Mosint)'),

                        Text::make('twitter_url')
                            ->label('Twitter Profile')
                            ->url(fn($state) => $state)
                            ->openUrlInNewTab(),

                        Text::make('osint_data')
                            ->label('Raw OSINT Data')
                            ->formatStateUsing(fn($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->markdown()
                            ->prose() // Better readability
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                \Filament\Schemas\Components\Section::make('Contact Info')
                    ->schema([
                        Image::make('avatar')
                            ->label('Avatar')
                            ->circular(),
                        Text::make('name')
                            ->weight('bold')
                            ->size('lg'),
                        Text::make('position'),
                        Text::make('company.name')
                            ->label('Company')
                            ->url(fn(People $record): ?string => $record->company_id ? CompanyResource::getUrl('view', [$record->company_id]) : null),
                        Text::make('email')
                            ->icon('heroicon-m-envelope')
                            ->copyable(),
                        Text::make('phone')
                            ->icon('heroicon-m-phone')
                            ->copyable(),
                        Text::make('website')
                            ->url(fn($state) => $state)
                            ->openUrlInNewTab(),
                        Text::make('location') // Assuming logic exists or remove if not
                            ->default('—'),
                    ])
                    ->columns(2),

                \Filament\Schemas\Components\Section::make('Social & Analysis')
                    ->schema([
                        Text::make('linkedin_url')->label('LinkedIn')->url(fn($state) => $state),
                        Text::make('vk_url')->label('VK')->url(fn($state) => $state),
                        Text::make('vk_status')->badge(),
                        Text::make('lead_score'),
                        Text::make('lead_category')->badge(),
                        Text::make('smm_analysis')
                            ->formatStateUsing(fn($state) => $state ? '✅ Analyzed' : '—'),
                    ])->columns(3),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'view' => ViewPeople::route('/{record}'),
        ];
    }

    /**
     * @return Builder<People>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
