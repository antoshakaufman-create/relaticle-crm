<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\VkActionService;
use Illuminate\Console\Command;

class EnrichVkLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:enrich-vk-links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and save VK Community links for companies that are missing them';

    /**
     * Execute the console command.
     */
    public function handle(VkActionService $vkService)
    {
        $this->info("Starting VK Link Enrichment...");

        // Select companies where vk_url is NULL or empty string
        $companies = Company::whereNull('vk_url')->orWhere('vk_url', '')->get();
        $total = $companies->count();

        $this->info("Found {$total} companies without VK links.");

        if ($total == 0) {
            $this->info("Nothing to enrich.");
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $foundCount = 0;

        foreach ($companies as $company) {
            try {
                // Try to find group
                // We pass name and domain (from website if available)
                $domain = null;
                if ($company->website) {
                    $domain = parse_url($company->website, PHP_URL_HOST) ?? $company->website;
                }

                $vkUrl = $vkService->findGroup($company->name, $domain);

                if ($vkUrl) {
                    $company->vk_url = $vkUrl;
                    $company->save();
                    $foundCount++;
                    // Optional: Log success to console? $this->line(" Found: $vkUrl");
                }

            } catch (\Exception $e) {
                // Log silently
            }

            $bar->advance();
            // Sleep to avoid aggressive rate limiting
            usleep(300000); // 0.3s
        }

        $bar->finish();
        $this->newLine();
        $this->info("Enrichment completed! Found {$foundCount} new VK links.");
    }
}
