<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\AiSummary;
use App\Services\AI\RecordSummaryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class GenerateRecordSummaryAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'generateSummary';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('AI Summary')
            ->icon(Heroicon::Sparkles)
            ->color('gray')
            ->modalHeading('AI Summary')
            ->modalDescription(fn (Model $record): string => 'AI-generated summary for this '.class_basename($record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (Model $record): View => $this->getSummaryView($record))
            ->registerModalActions([
                $this->makeRegenerateAction(),
                $this->makeCopyAction(),
            ]);
    }

    private function makeRegenerateAction(): Action
    {
        return Action::make('regenerate')
            ->label('Regenerate')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->action(fn (Model $record) => $this->regenerateSummary($record));
    }

    private function makeCopyAction(): Action
    {
        return Action::make('copy')
            ->label('Copy')
            ->icon(Heroicon::Clipboard)
            ->color('gray')
            ->extraAttributes(function (Model $record): array {
                // @phpstan-ignore nullsafe.neverNull (defensive code for models without HasAiSummary trait)
                $summaryText = $this->getCachedSummary($record)?->summary ?? '';

                return [
                    'x-on:click' => 'window.navigator.clipboard.writeText('.json_encode($summaryText).'); $tooltip(\'Copied!\')',
                ];
            });
    }

    private function getSummaryView(Model $record): View
    {
        try {
            $summary = $this->summaryService()->getSummary($record);
            return view('filament.actions.ai-summary', [
                'summary' => $summary,
            ]);
        } catch (Throwable $e) {
            // Return error view if summary generation fails
            return view('filament.actions.ai-summary-error', [
                'error' => $e->getMessage(),
                'hint' => $this->getErrorMessage($e),
            ]);
        }
    }
    
    private function getErrorMessage(Throwable $e): string
    {
        $message = $e->getMessage();
        
        if (str_contains($message, 'YandexGPT') || str_contains($message, 'API key')) {
            return 'Проверьте настройки YandexGPT в .env файле (YANDEX_GPT_API_KEY и YANDEX_FOLDER_ID)';
        }
        
        return 'Проверьте логи для получения дополнительной информации';
    }

    private function getCachedSummary(Model $record): ?AiSummary
    {
        if (! method_exists($record, 'aiSummary')) {
            return null;
        }

        return $record->aiSummary; // @phpstan-ignore property.notFound
    }

    private function regenerateSummary(Model $record): void
    {
        try {
            $this->summaryService()->getSummary($record, regenerate: true);

            Notification::make()
                ->title('Summary regenerated')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Failed to regenerate summary')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function summaryService(): RecordSummaryService
    {
        return app(RecordSummaryService::class);
    }
}
