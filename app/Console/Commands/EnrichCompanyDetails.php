<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnrichCompanyDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:enrich-company-details';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich companies with legal details from DaData (Light Tariff)';

    // Hardcoded keys for simplicity as per user context
    private $apiKey = 'd727a93a800dd5572305eb876d66c44c3099813a';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting Company Enrichment (DaData Light)...");

        $companies = Company::whereNull('inn')->orWhere('inn', '')->get();
        $total = $companies->count();
        $this->info("Found {$total} companies without INN.");

        if ($total === 0) {
            // Optional: allow forcing re-check
            $this->info("Nothing to enrich. Exiting.");
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        foreach ($companies as $company) {
            try {
                // Search by Name + Domain hint if possible?
                // Suggest API implies prioritizing locations etc.
                // We'll just search by name.

                $response = Http::withHeaders([
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party', [
                            'query' => $company->name,
                            'count' => 1
                        ]);

                if ($updated < 5) {
                    $this->info("Checking {$company->name}...");
                    $this->info("Status: " . $response->status());
                }

                if ($response->successful()) {
                    $json = $response->json();
                    if ($updated < 5) {
                        $this->info("Suggestions count: " . count($json['suggestions'] ?? []));
                    }
                    if (!empty($json['suggestions'])) {
                        $data = $json['suggestions'][0]['data'];
                        $value = $json['suggestions'][0]['value'];

                        $company->legal_name = $value;
                        $company->inn = $data['inn'] ?? null;
                        $company->ogrn = $data['ogrn'] ?? null;
                        $company->kpp = $data['kpp'] ?? null;

                        if (isset($data['management'])) {
                            $company->management_name = $data['management']['name'] ?? null;
                            $company->management_post = $data['management']['post'] ?? null;
                        }

                        $company->okved = $data['okved'] ?? null;
                        $company->status = $data['state']['status'] ?? null;

                        // Also update address if empty?
                        if (empty($company->address_line_1) && isset($data['address']['value'])) {
                            $company->address_line_1 = $data['address']['value'];
                        }

                        $company->save();
                        $updated++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error saving {$company->name}: " . $e->getMessage());
            }

            $bar->advance();
            usleep(100000); // 10 calls/sec max usually, so 100ms is safe (10 requests/sec)
        }

        $bar->finish();
        $this->newLine();
        $this->info("Enrichment Complete. Updated {$updated} companies.");
    }
}
