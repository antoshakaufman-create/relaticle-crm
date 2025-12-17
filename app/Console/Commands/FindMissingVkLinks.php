<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Services\VkActionService;

class FindMissingVkLinks extends Command
{
    protected $signature = 'vk:find-missing';
    protected $description = 'Find missing VK links using updated scoring logic';

    public function handle(VkActionService $vkService)
    {
        $companies = Company::whereNull('vk_url')->orWhere('vk_url', '')->get();
        $count = $companies->count();

        $this->info("Found {$count} companies without VK link.");

        $processed = 0;
        $found = 0;

        foreach ($companies as $company) {
            $processed++;
            $this->info("[$processed/$count] Processing: {$company->name} (Legal: {$company->legal_name})...");

            try {
                $domain = $company->website;
                $legalName = $company->legal_name;
                $address = $company->address_line_1;

                $url = $vkService->findGroup($company->name, $domain, $legalName, $address);

                if ($url) {
                    $company->update(['vk_url' => $url]);
                    $found++;
                    $this->info("   -> FOUND: {$url}");
                } else {
                    $this->info("   -> NOT FOUND");
                }
            } catch (\Exception $e) {
                $this->error("   -> ERROR: " . $e->getMessage());
            }

            // Delay
            usleep(100000);
        }

        $this->info("Done! Found {$found} new VK links out of {$count}.");
    }
}
