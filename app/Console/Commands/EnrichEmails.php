<?php

namespace App\Console\Commands;

use App\Models\People;
use App\Services\LeadGeneration\EmailDiscoveryService;
use App\Services\AI\YandexGPTService; // Add this
use Illuminate\Console\Command;

class EnrichEmails extends Command
{
    protected $signature = 'app:enrich-emails {--limit=50}';
    protected $description = 'Find corporate emails for AI-generated employees';

    public function handle(EmailDiscoveryService $discovery, YandexGPTService $gpt) // Inject GPT
    {
        $limit = $this->option('limit');

        $people = People::where('creation_source', 'AI_GENERATED')
            ->whereNull('email') // Only process those without email
            ->with('company')
            ->limit($limit) // Process small batches
            ->get();

        $this->info("Found {$people->count()} people to find emails for.");

        foreach ($people as $person) {
            $company = $person->company;
            if (!$company)
                continue;

            $website = $company->website;

            // If website is missing, ask GPT
            if (empty($website) || $website === 'NULL') {
                $this->info("   Asking GPT for website of: {$company->name}...");
                $website = $this->askGptForWebsite($gpt, $company);

                if ($website) {
                    $company->update(['website' => $website]);
                    $this->info("   -> Found & Saved: $website");
                }
            }

            if (empty($website)) {
                $this->warn("Skipping {$person->name} (No company website found)");
                continue;
            }

            $this->info("Probing emails for: {$person->name} @ {$website}");

            // Pass website explicitly if needed, but service extracts from person->company->website
            // We need to ensure service uses the string we found
            // Refactor service to accept domain string? No, simpler to set it temporarily or fix service.

            // Easier: Update service to accept string domain, or just ensure model attribute is set.
            $email = $discovery->findCorporateEmail($person, $website); // Pass explicit website

            if ($email) {
                $person->update(['email' => $email]);
                $this->info("   -> [SUCCESS] Found: $email");
            } else {
                $this->warn("   -> [FAIL] No valid email found.");
            }

            // Sleep slightly to respect SMTP servers
            usleep(500000);
        }
    }

    private function askGptForWebsite(YandexGPTService $gpt, \App\Models\Company $company): ?string
    {
        $prompt = "Напиши только домен официального сайта компании \"{$company->name}\" ({$company->address_line_1}). " .
            "Если не знаешь - верни null. Пример: google.com. БЕЗ ЛИШНЕГО ТЕКСТА.";

        $result = $gpt->search($prompt);
        $text = trim($result['content'] ?? '');

        // Basic cleanup
        $text = str_replace(['https://', 'http://', '/'], '', $text);

        if (str_contains($text, '.') && !str_contains($text, 'null') && strlen($text) < 50) {
            return $text;
        }
        return null;
    }
}
