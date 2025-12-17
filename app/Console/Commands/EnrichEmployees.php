<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\People;
use App\Services\LeadGeneration\YandexGPTLeadService;
use Illuminate\Console\Command;

class EnrichEmployees extends Command
{
    protected $signature = 'app:enrich-employees {--limit=50}';
    protected $description = 'Find employees (LinkedIn) for AI-generated companies using YandexGPT';

    public function handle(YandexGPTLeadService $gptService)
    {
        $limit = $this->option('limit');

        // Find AI companies that don't have people yet
        $companies = Company::where('creation_source', 'AI_GENERATED')
            ->doesntHave('people')
            ->limit($limit)
            ->get();

        $this->info("Found {$companies->count()} companies to enrich.");

        foreach ($companies as $company) {
            $this->info("Enriching: {$company->name} ({$company->address_line_1})");

            $employees = $gptService->findEmployeesForCompany($company);

            // Sleep to avoid rate limits
            sleep(1);

            if (empty($employees)) {
                $this->warn("   -> No employees found by GPT.");
                continue;
            }

            foreach ($employees as $emp) {
                // Skip if name is empty or null
                if (empty($emp['name']) || $emp['name'] === 'null')
                    continue;

                People::create([
                    'company_id' => $company->id,
                    'team_id' => $company->team_id ?? 1,
                    'name' => $emp['name'],
                    'position' => $emp['position'],
                    'linkedin_url' => $emp['linkedin_url'] ?? null,
                    'creation_source' => 'AI_GENERATED',
                    // 'city' => 'Москва', // Removed to avoid sql error
                    'notes' => 'Sourced via YandexGPT (LinkedIn Search)',
                ]);
                $this->info("   -> Added: {$emp['name']} ({$emp['position']})");
            }
        }

        $this->info("Done.");
    }

    // private function buildPrompt removed (moved to service)
    // private function parseResponse removed (moved to service)
}
