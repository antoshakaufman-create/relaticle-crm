<?php
/**
 * Analyze VK Activity Dates
 * 
 * 1. Iterate all contacts with VK URL
 * 2. Resolve Group ID
 * 3. Fetch last 5 posts
 * 4. Determine "Last Activity Date" (ignoring pinned posts if newer exists)
 * 5. Categorize:
 *    - ACTIVE (2025)
 *    - INACTIVE (2023-2024)
 *    - DEAD (2022 or older)
 * 6. Save status to Notes for easy export
 * 
 * Run: php8.5 /tmp/analyze_vk_status.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use Illuminate\Support\Facades\Http;

$vkToken = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';

echo "=== VK Activity Status Analysis ===\n\n";

function getVkGroupId($screenName, $token)
{
    try {
        $response = Http::get("https://api.vk.com/method/utils.resolveScreenName", [
            'screen_name' => $screenName,
            'access_token' => $token,
            'v' => '5.131'
        ]);
        if (isset($response['response']['object_id'])) {
            return $response['response']['object_id'];
        }
    } catch (\Exception $e) {
    }
    return null;
}

function getLastPostDate($ownerId, $token)
{
    $ownerId = '-' . abs($ownerId);
    try {
        $response = Http::get("https://api.vk.com/method/wall.get", [
            'owner_id' => $ownerId,
            'count' => 5, // Check top 5 to avoid pinned post trap
            'access_token' => $token,
            'v' => '5.131'
        ]);

        $items = $response['response']['items'] ?? [];
        if (empty($items))
            return 0;

        $maxDate = 0;
        foreach ($items as $item) {
            if ($item['date'] > $maxDate) {
                $maxDate = $item['date'];
            }
        }
        return $maxDate;
    } catch (\Exception $e) {
        return 0;
    }
}

$contacts = People::whereNotNull('vk_url')->where('vk_url', '!=', '')->get();

echo "Contacts to check: " . count($contacts) . "\n";

$stats = ['Active' => 0, 'Inactive' => 0, 'Dead' => 0, 'Error' => 0];
$processed = 0;
$processedUrls = []; // cache results for duplicates

foreach ($contacts as $contact) {
    $processed++;
    $vkUrl = $contact->vk_url;

    // Check cache
    if (isset($processedUrls[$vkUrl])) {
        $statusInfo = $processedUrls[$vkUrl];
    } else {
        // Extract screen name
        $path = parse_url($vkUrl, PHP_URL_PATH);
        $screenName = trim(str_replace('/', '', $path));

        if (!$screenName) {
            $processedUrls[$vkUrl] = ['status' => 'Error', 'label' => 'Invalid URL'];
            continue;
        }

        $label = "Error";
        $statusKey = "Error";

        $groupId = getVkGroupId($screenName, $vkToken);

        if ($groupId) {
            $lastDate = getLastPostDate($groupId, $vkToken);

            if ($lastDate > 0) {
                $year = date('Y', $lastDate);
                $dateStr = date('d.m.Y', $lastDate);

                if ($year == 2025) {
                    $label = "ACTIVE 2025 (Last: $dateStr)";
                    $statusKey = "Active";
                } elseif ($year >= 2023) {
                    $label = "INACTIVE (Last: $dateStr)";
                    $statusKey = "Inactive";
                } else {
                    $label = "DEAD (Last: $dateStr)";
                    $statusKey = "Dead";
                }
            } else {
                $label = "DEAD (No posts)";
                $statusKey = "Dead";
            }
        } else {
            $label = "Error (Page not found)";
            $statusKey = "Error";
        }

        $statusInfo = ['status' => $statusKey, 'label' => $label];
        $processedUrls[$vkUrl] = $statusInfo;

        // Rate limit
        usleep(300000);
    }

    // Update stats
    $stats[$statusInfo['status']]++;

    // Log
    echo "[$processed] $vkUrl -> " . $statusInfo['label'] . "\n";

    // Save to Notes
    $notes = $contact->notes ?? '';
    // Clean old status
    $notes = preg_replace('/VK_STATUS: .*$/m', '', $notes);
    $notes = trim($notes);
    $notes .= "\nVK_STATUS: " . $statusInfo['label'];
    $contact->update(['notes' => $notes]);
}

echo "\n=== Final Stats ===\n";
print_r($stats);
