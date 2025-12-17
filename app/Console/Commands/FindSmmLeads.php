<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FindSmmLeads extends Command
{
    protected $signature = 'app:find-smm-leads';
    protected $description = 'Find SMM leads with revenue > 50M using DaData';

    private $apiKey = 'd727a93a800dd5572305eb876d66c44c3099813a';
    private $secretKey = '9b0a6065099a43fb24e0d4ac98f95482107b2d29';

    public function handle()
    {
        $this->info("Starting SMM Lead Search...");

        $companies = Company::pluck('name')->unique()->filter()->values();
        $total = $companies->count();
        $this->info("Found {$total} companies to check.");

        $csvPath = base_path('smm_leads.csv');
        $fp = fopen($csvPath, 'w');
        fputcsv($fp, ['Company', 'INN', 'Revenue (Rub)', 'Status', 'Industry', 'Website', 'DaData Link']);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($companies as $name) {
            $info = $this->getCompanyInfo($name);
            if ($info && isset($info['data'])) {
                $data = $info['data'];
                $inn = $data['inn'] ?? '-';
                $finance = $data['finance'] ?? [];
                $income = $finance['income'] ?? 0;

                // Income is usually in rubles.
                // Filter > 50,000,000

                $industry = $data['okved'] ?? '-';
                $status = $data['state']['status'] ?? 'UNKNOWN';

                $isInteresting = false;
                if ($income > 50000000) {
                    $isInteresting = true;
                }

                // If the user wants to populate the DB, we could doing it here.
                // For now, just CSV.

                if ($isInteresting) {
                    fputcsv($fp, [
                        $name,
                        $inn,
                        number_format($income, 0, '.', ' '),
                        $status,
                        $industry,
                        $data['url'] ?? '',
                        "https://checko.ru/company/{$inn}" // Helpful link
                    ]);
                }
            }
            $bar->advance();
            // Rate limit: 10 calls per second is generic limit, we do sequential so it's fine.
            usleep(200000); // 200ms
        }

        $bar->finish();
        fclose($fp);
        $this->newLine();
        $this->info("Done! Saved to {$csvPath}");
    }

    private function getCompanyInfo($name)
    {
        // 1. Suggest to get INN/Basic
        $suggest = Http::withHeaders([
            'Authorization' => 'Token ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party', [
                    'query' => $name,
                    'count' => 1
                ])->json();

        if (empty($suggest['suggestions']))
            return null;

        $basic = $suggest['suggestions'][0];
        $inn = $basic['data']['inn'] ?? null;

        if (!$inn)
            return $basic; // Return what we have

        // 2. FindById to get Finance (Full details)
        // Some plans provide finance in suggest, some need findById.
        // It's safer to call findById if we want deep data.

        $details = Http::withHeaders([
            'Authorization' => 'Token ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party', [
                    'query' => $inn
                ])->json();

        return $details['suggestions'][0] ?? $basic;
    }
}
