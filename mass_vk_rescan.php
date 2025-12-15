<?php

use App\Models\Company;
use App\Services\VkActionService;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new VkActionService();
echo "Starting Full Database VK Rescan...\n";

// Process ALL companies in chunks
Company::chunk(50, function ($companies) use ($service) {
    foreach ($companies as $company) {
        $domain = null;
        if ($company->website) {
            $host = parse_url($company->website, PHP_URL_HOST);
            $domain = $host ? str_ireplace('www.', '', $host) : null;
        }

        $currentUrl = $company->vk_url;
        $name = $company->name;

        // 1. If link exists, verify it
        if ($currentUrl) {
            $isValid = $service->verifyLinkRelevance($currentUrl, $name, $domain);
            if ($isValid) {
                // Link is good, keep it.
                // echo "[OK] $name -> $currentUrl\n";
                continue;
            } else {
                echo "[FIX] Invalid link for '$name' ('$currentUrl'). Re-searching...\n";
                // If invalid, we drop down to re-search
            }
        } else {
            echo "[NEW] Searching for '$name'...\n";
        }

        // 2. Search (New Logic)
        $newUrl = $service->findGroup($name, $domain);

        if ($newUrl) {
            if ($newUrl !== $currentUrl) {
                echo " -> FOUND: $newUrl\n";
                $company->vk_url = $newUrl;
                $company->vk_status = 'active';
                $company->save();
            } else {
                // If found same as bad currentUrl, then verifyLinkRelevance and findGroup disagree?
                // verifyLinkRelevance acts as a filter *inside* findGroup too.
                // So this case implies findGroup returned it AGAIN.
                // If findGroup returned it, it must have passed validateGroupRelevance inside findGroup.
                // So verifyLinkRelevance and findGroup should be consistent.
                // If inconsistent, we trust findGroup's fresh result.
                echo " -> RE-CONFIRMED: $newUrl (Logic accepted it)\n";
            }
        } else {
            if ($currentUrl) {
                echo " -> NO MATCH FOUND. Clearing bad link.\n";
                $company->vk_url = null;
                $company->vk_status = 'pending';
                $company->save();
            } else {
                echo " -> No match found.\n";
            }
        }

        // Sleep slightly to avoid rate limit
        usleep(200000); // 0.2s
    }
});

echo "Rescan Complete.\n";
