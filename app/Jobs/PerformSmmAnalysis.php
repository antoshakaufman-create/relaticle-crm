<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\VkActionService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PerformSmmAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes timeout for the job

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Company $company
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(VkActionService $vkService): void
    {
        $url = $this->company->vk_url;

        if (!$url) {
            return;
        }

        try {
            $result = $vkService->analyzeGroup($url);

            if (isset($result['error'])) {
                Notification::make()
                    ->title('SMM Analysis Failed')
                    ->body("Company: {$this->company->name}\nError: {$result['error']}")
                    ->danger()
                    ->sendToDatabase($this->company->accountOwner ?? \App\Models\User::first());
            } else {
                $date = now()->format('Y-m-d H:i');
                // Create Note record
                $noteTitle = "SMM Basic Analysis [$date]";
                $note = new \App\Models\Note();
                $note->title = $noteTitle;
                $note->team_id = $this->company->team_id;
                $note->creator_id = $this->company->accountOwner?->id ?? $this->company->creator_id;
                $note->save();

                // Attach to Company
                $note->companies()->attach($this->company->id);

                // Save Body Custom Field (ID 7)
                $bodyContent = "### SMM Basic Analysis\n" . $result['smm_analysis'];
                \Illuminate\Support\Facades\DB::table('custom_field_values')->insert([
                    'tenant_id' => $this->company->team_id,
                    'entity_type' => \App\Models\Note::class,
                    'entity_id' => $note->id,
                    'custom_field_id' => 7, // Body Field ID
                    'text_value' => $bodyContent,
                ]);

                $this->company->update([
                    'smm_analysis' => $result['smm_analysis'],
                    'vk_status' => $result['vk_status'],
                    'er_score' => $result['er_score'],
                    'posts_per_month' => $result['posts_per_month'],
                    'lead_score' => $result['lead_score'],
                    'lead_category' => $result['lead_category'],
                    // 'notes' => removed as column doesn't exist
                ]);

                Notification::make()
                    ->title('SMM Analysis Complete')
                    ->body("Analysis finished for {$this->company->name}.")
                    ->success()
                    ->sendToDatabase($this->company->accountOwner ?? \App\Models\User::first());
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('SMM Analysis Error')
                ->body("Exception for {$this->company->name}: " . $e->getMessage())
                ->danger()
                ->sendToDatabase($this->company->accountOwner ?? \App\Models\User::first());
            throw $e;
        }
    }
}
