<?php
/**
 * Find Missing VK Links using Official API
 * 
 * 1. Find contacts without VK URL
 * 2. Search for company groups using groups.search
 * 3. Update VK URL if a high-confidence match is found
 * 
 * Run: php8.5 /tmp/find_vk_links.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use Illuminate\Support\Facades\Http;

$vkToken = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';

echo "=== Find Missing VK Links (API) ===\n\n";

function searchVkGroup($query, $token)
{
    try {
        // Clean query (remove ООО, ЗАО, PAO, content in brackets)
        $cleanQuery = preg_replace('/(ООО|ЗАО|ПАО|АО)\s+/iu', '', $query);
        $cleanQuery = preg_replace('/\s*\(.*?\)/u', '', $cleanQuery);
        $cleanQuery = trim($cleanQuery);

        if (mb_strlen($cleanQuery) < 2)
            return null;

        $response = Http::get("https://api.vk.com/method/groups.search", [
            'q' => $cleanQuery,
            'count' => 1, // Get top result
            'access_token' => $token,
            'v' => '5.131',
            'fields' => 'members_count,verified'
        ]);

        $data = $response->json();
        $items = $data['response']['items'] ?? [];

        if (!empty($items)) {
            $group = $items[0];

            // Basic validation:
            // 1. If verified - accept immediately
            // 2. OR if members_count > 50 (filter out empty placeholders)
            if (($group['verified'] ?? 0) == 1 || ($group['members_count'] ?? 0) > 50) {
                return [
                    'url' => 'https://vk.com/' . $group['screen_name'],
                    'name' => $group['name'],
                    'members' => $group['members_count'] ?? 0,
                    'verified' => $group['verified'] ?? 0 ? 'yes' : 'no'
                ];
            }
        }
    } catch (\Exception $e) {
    }
    return null;
}

// Get contacts WITHOUT VK URL
$contacts = People::whereNull('vk_url')->orWhere('vk_url', '')->get();

// Group by company
$companyContacts = [];
foreach ($contacts as $contact) {
    $companyName = '';
    $notes = $contact->notes ?? '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $m)) {
        $companyName = trim($m[1]);
    } else {
        $companyName = $contact->company ? $contact->company->name : null;
    }

    if (!$companyName || $companyName == 'Не указано' || mb_strlen($companyName) < 3)
        continue;

    if (!isset($companyContacts[$companyName])) {
        $companyContacts[$companyName] = [];
    }
    $companyContacts[$companyName][] = $contact;
}

echo "Companies to search: " . count($companyContacts) . "\n\n";

$found = 0;
$skipped = 0;
$processed = 0;

foreach ($companyContacts as $companyName => $contactsList) {
    $processed++;
    echo "[$processed/" . count($companyContacts) . "] $companyName... ";

    $result = searchVkGroup($companyName, $vkToken);

    if ($result) {
        echo "FOUND: " . $result['url'] . " (" . $result['members'] . " subs, verified: " . $result['verified'] . ")\n";

        // Update all contacts
        foreach ($contactsList as $contact) {
            $contact->update(['vk_url' => $result['url']]);
        }
        $found++;
    } else {
        echo "Not found / Low quality\n";
        $skipped++;
    }

    // Rate limit
    usleep(350000); // ~3 requests per second
}

echo "\n=== Results ===\n";
echo "Companies processed: $processed\n";
echo "Found VK links: $found\n";
echo "Skipped: $skipped\n";
echo "Total contacts with VK now: " . People::whereNotNull('vk_url')->where('vk_url', '!=', '')->count() . "\n";
