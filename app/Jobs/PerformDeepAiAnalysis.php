<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\People;
use App\Services\VkActionService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PerformDeepAiAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Company|People $record
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(VkActionService $vkService): void
    {
        $url = $this->record->vk_url;

        if (!$url) {
            return;
        }

        try {
            // This is the heavy part
            $result = $vkService->performDeepAnalysis($url);

            if (isset($result['error'])) {
                Notification::make()
                    ->title('Deep AI Analysis Failed')
                    ->body("Record: {$this->record->name}\nError: {$result['error']}")
                    ->danger()
                    ->sendToDatabase($this->record->accountOwner ?? $this->record->creator ?? \App\Models\User::first());
            } else {
                $date = now()->format('Y-m-d H:i');

                // Create Note record
                $noteTitle = "AI SMM Analysis [$date]";
                $note = new \App\Models\Note();
                $note->title = $noteTitle;
                $note->team_id = $this->record->team_id;
                $note->creator_id = $this->record->accountOwner?->id ?? $this->record->creator_id;
                $note->save();

                // Attach
                if ($this->record instanceof Company) {
                    $note->companies()->attach($this->record->id);
                } elseif ($this->record instanceof People) {
                    $note->people()->attach($this->record->id);
                }

                // Save Body Custom Field (ID 7)
                $bodyContent = "### SMM Deep Analysis (AI)\n" . $result['smm_analysis'];
                \Illuminate\Support\Facades\DB::table('custom_field_values')->insert([
                    'tenant_id' => $this->record->team_id,
                    'entity_type' => \App\Models\Note::class,
                    'entity_id' => $note->id,
                    'custom_field_id' => 7, // Body Field ID
                    'text_value' => $bodyContent,
                ]);

                $this->record->update([
                    'smm_analysis' => $result['smm_analysis'],
                ]);

                Notification::make()
                    ->title('AI Analysis Complete')
                    ->body("AI Insights generated for {$this->record->name}.")
                    ->success()
                    ->sendToDatabase($this->record->accountOwner ?? $this->record->creator ?? \App\Models\User::first());
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('AI Analysis Error')
                ->body("Exception for {$this->record->name}: " . $e->getMessage())
                ->danger()
                ->sendToDatabase($this->record->accountOwner ?? $this->record->creator ?? \App\Models\User::first());
            throw $e;
        }
    }
}
