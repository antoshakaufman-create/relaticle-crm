<?php

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Company;
use Illuminate\Support\Facades\Http;

$vkToken = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';

echo "=== VK Group Finder for MOEX Companies ===\n\n";

// Get ALL companies without VK URL
$companies = Company::where(function ($q) {
    $q->whereNull('vk_url')->orWhere('vk_url', '');
})
    ->get();

echo "Found " . $companies->count() . " companies to process.\n";

foreach ($companies as $company) {
    echo "Searching for {$company->name}... ";

    // Basic cleaning: "Sberbank of Russia" -> "Sberbank"
    // Using preg_replace to remove text in parens if that helps, or just raw name
    $query = $company->name;

    // Explicit cleaning for known complex names if needed (but API search is usually smart)

    try {
        $response = Http::get('https://api.vk.com/method/groups.search', [
            'q' => $query,
            'count' => 5,
            'access_token' => $vkToken,
            'v' => '5.131',
            'verified' => 1 // Prefer verified? Not a filter param, sort param is.
            // sort=0 (default) is by relevance.
        ]);

        $items = $response['response']['items'] ?? [];

        $found = null;

        // 1. Try to find VERIFIED group first
        foreach ($items as $item) {
            if (($item['is_verified'] ?? 0) == 1) {
                $found = $item;
                break;
            }
        }

        // 2. If no verify, take first
        if (!$found && !empty($items)) {
            $found = $items[0];
        }

        if ($found) {
            $screenName = $found['screen_name'];
            $url = "https://vk.com/$screenName";
            echo "Found: $url ({$found['name']}) [Verified: " . ($found['is_verified'] ?? 0) . "]\n";

            $company->update(['vk_url' => $url]);
        } else {
            echo "Not found.\n";
        }

    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    // Rate limit
    usleep(500000);
}

echo "Done.\n";
