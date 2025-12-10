<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Services\AI\HybridAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class EnrichLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Lead $lead
    ) {
        $this->onQueue('enrichment');
    }

    /**
     * Execute the job.
     */
    public function handle(HybridAIService $aiService): void
    {
        try {
            if (!$this->lead->company_name) {
                return;
            }

            $companyInfo = $aiService->searchCompanyInfo($this->lead->company_name);

            if ($companyInfo && !empty($companyInfo['data'])) {
                $enrichmentData = $this->lead->enrichment_data ?? [];
                $enrichmentData = array_merge($enrichmentData, $companyInfo['data']);

                $this->lead->update([
                    'enrichment_data' => $enrichmentData,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error enriching lead in job: '.$e->getMessage(), [
                'lead_id' => $this->lead->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
