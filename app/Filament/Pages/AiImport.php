<?php

namespace App\Filament\Pages;

use App\Services\AI\SmartImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AiImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $title = 'AI Smart Import';
    protected static string $view = 'filament.pages.ai-import';

    public static function getNavigationGroup(): ?string
    {
        return __('resources.workspace.label');
    }

    public ?array $data = [];
    public ?array $mapping = [];
    public bool $analyzed = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('target_model')
                    ->label('Target Data Type')
                    ->options([
                        'Lead' => 'Leads',
                        'People' => 'People',
                        'Company' => 'Companies',
                    ])
                    ->required()
                    ->default('Lead'),

                FileUpload::make('file')
                    ->label('Upload Excel/CSV File')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                    ->required(),
            ])
            ->statePath('data');
    }

    public function analyze(): void
    {
        $data = $this->form->getState();

        /** @var TemporaryUploadedFile $file */
        $file = $data['file'];
        // In local/production, file is stored in tmp. We need the path.
        $path = $file->getRealPath();

        /** @var SmartImportService $service */
        $service = app(SmartImportService::class);

        try {
            $result = $service->analyzeFile($path, $data['target_model']);

            if (empty($result)) {
                Notification::make()->title('Analysis Failed')->danger()->send();
                return;
            }

            $this->mapping = $result['mapping'];
            $this->analyzed = true;

            Notification::make()->title('AI Analysis Complete')
                ->body('AI has suggested column mappings. Click "Run Import" to confirm.')
                ->success()->send();

        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function runImport(): void
    {
        $data = $this->form->getState();
        /** @var TemporaryUploadedFile $file */
        $file = $data['file'];
        $path = $file->getRealPath();

        /** @var SmartImportService $service */
        $service = app(SmartImportService::class);

        try {
            $count = $service->processImport($path, $data['target_model'], $this->mapping);

            Notification::make()->title('Import Successful')
                ->body("Successfully imported {$count} records.")
                ->success()->send();

            $this->reset('data', 'mapping', 'analyzed');
            $this->form->fill();

        } catch (\Exception $e) {
            Notification::make()->title('Import Error')->body($e->getMessage())->danger()->send();
        }
    }
}
