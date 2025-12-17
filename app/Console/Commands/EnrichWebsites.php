<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AI\YandexGPTService;
use Illuminate\Console\Command;

class EnrichWebsites extends Command
{
    protected $signature = 'app:enrich-websites {--limit=50}';
    protected $description = 'Find missing websites for companies using YandexGPT';

    public function handle(YandexGPTService $gpt)
    {
        $limit = $this->option('limit');

        $companies = Company::where(function ($q) {
            $q->whereNull('website')->orWhere('website', '');
        })
            ->limit($limit)
            ->get();

        $count = $companies->count();
        $this->info("Found {$count} companies without websites.");

        if ($count === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($companies as $company) {

            // Skip if company name is essentially empty or invalid
            if (strlen($company->name) < 2) {
                $bar->advance();
                continue;
            }

            try {
                $website = $this->askGptForWebsite($gpt, $company);

                if ($website) {
                    $company->update(['website' => $website]);
                    // Log success without breaking progress bar? 
                    // Use clear/write? Or just let bar verify.
                }
            } catch (\Exception $e) {
                // Ignore errors to keep going
            }

            $bar->advance();
            // Rate limit slightly
            usleep(200000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done.");
    }

    private function askGptForWebsite(YandexGPTService $gpt, Company $company): ?string
    {
        // Add city/address for better context
        $context = $company->address_line_1 ? "({$company->address_line_1})" : "(Russia)";

        $prompt = "Напиши только домен официального сайта компании \"{$company->name}\" {$context}. " .
            "Если не знаешь - верни null. Пример: google.com. БЕЗ ЛИШНЕГО ТЕКСТА.";

        $result = $gpt->search($prompt);
        $text = trim($result['content'] ?? '');

        // Cleanup
        $text = str_replace(['https://', 'http://', '/'], '', $text);
        $text = strtolower($text);

        // Validation
        if (str_contains($text, '.') && !str_contains($text, 'null') && !str_contains($text, ' ') && strlen($text) < 50) {
            return $text;
        }
        return null;
    }
}
