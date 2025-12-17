<?php

use App\Models\Company;
use App\Services\VkActionService;

echo "=== Simulating 'Find VK Link' Button for Kion ===\n";

// Find Kion (using ID 249 or just name match)
$companies = Company::where('name', 'LIKE', '%Kion%')->orWhere('name', 'LIKE', '%Кион%')->get();

$service = new VkActionService();

foreach ($companies as $company) {
    echo "Processing Company ID: {$company->id} ({$company->name})\n";
    echo "Current VK URL: " . ($company->vk_url ?? 'NULL') . "\n";

    // Simulate Action Logic from ViewCompany.php
    $domain = $company->website;
    // Logic extracted from ViewCompany.php
    $url = $service->findGroup($company->name, $domain, $company->legal_name, $company->address_line_1);

    echo "Found URL: " . ($url ?? 'NULL') . "\n";

    if ($url) {
        $company->update(['vk_url' => $url]);
        echo "UPDATED DB Record to: $url\n";
    } else {
        echo "No valid URL found. Record unchanged.\n";
    }
    echo "---------------------------------\n";
}
