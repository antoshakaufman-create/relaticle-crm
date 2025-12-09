<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Services\AI\YandexGPTService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Auth;

class CompanyDiscovery extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';
    protected static ?string $navigationLabel = 'Поиск компаний';
    protected static ?string $title = 'AI Поиск компаний';
    protected static string $view = 'filament.pages.company-discovery';

    public ?array $data = [];
    public array $results = [];
    public bool $isSearching = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('query')
                    ->label('Запрос')
                    ->placeholder('Например: Строительные компании в Казани')
                    ->required()
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        $data = $this->form->getState();
        $query = $data['query'];
        $this->isSearching = true;

        try {
            /** @var YandexGPTService $service */
            $service = app(YandexGPTService::class);
            $this->results = $service->discoverCompanies($query);

            if (empty($this->results)) {
                Notification::make()
                    ->warning()
                    ->title('Ничего не найдено')
                    ->body('Попробуйте уточнить запрос.')
                    ->send();
            } else {
                Notification::make()
                    ->success()
                    ->title('Поиск завершен')
                    ->body('Найдено ' . count($this->results) . ' компаний.')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Ошибка поиска')
                ->body($e->getMessage())
                ->send();
            $this->results = [];
        } finally {
            $this->isSearching = false;
        }
    }

    public function importCompany(string $name, string $url, string $description): void
    {
        // Check duplication
        $exists = Company::where('name', $name)->exists();

        if ($exists) {
            Notification::make()
                ->warning()
                ->title('Дубликат')
                ->body("Компания '$name' уже есть в базе.")
                ->send();
            return;
        }

        $company = new Company();
        $company->name = $name;
        $company->domain = parse_url($url, PHP_URL_HOST) ?? $url;
        $company->about = $description;

        // Link to current team if available
        if ($user = Auth::user()) {
            if ($user->currentTeam) {
                $company->team_id = $user->currentTeam->id;
            }
            $company->created_by = $user->id; // Assuming created_by/creator_id exists via trait
        }

        $company->save();

        Notification::make()
            ->success()
            ->title('Компания создана')
            ->body("Компания '$name' успешно добавлена.")
            ->send();
    }
}
