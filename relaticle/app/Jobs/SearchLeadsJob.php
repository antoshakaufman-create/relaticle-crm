<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LeadGeneration\HybridLeadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SearchLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $query,
        public readonly array $filters = [],
        public readonly ?int $teamId = null,
    ) {
        $this->onQueue('lead-generation');
    }

    /**
     * Execute the job.
     */
    public function handle(HybridLeadService $leadService): void
    {
        try {
            $leads = $leadService->searchLeads($this->query, $this->filters);

            Log::info('Leads search completed', [
                'query' => $this->query,
                'filters' => $this->filters,
                'leads_found' => $leads->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching leads in job: '.$e->getMessage(), [
                'query' => $this->query,
                'filters' => $this->filters,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
