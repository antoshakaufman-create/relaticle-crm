<?php

use App\Models\Company;
use App\Services\VkActionService;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new VkActionService();
$name = 'M.Video';
$badUrl = 'https://vk.com/club213276384';

// 1. Analyze Bad URL
echo "Analyzing Bad URL: $badUrl\n";
$screenName = str_replace('https://vk.com/', '', $badUrl);
$token = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';

$response = Http::get('https://api.vk.com/method/groups.getById', [
    'group_id' => $screenName,
    'fields' => 'site,description,status,verified,members_count',
    'access_token' => $token,
    'v' => '5.131'
]);
$group = $response['response'][0] ?? null;
if ($group) {
    echo " - Name: {$group['name']}\n";
    echo " - Verified: {$group['verified']}\n";
    // Check validation
    $valid = $service->verifyLinkRelevance($badUrl, $name, null);
    echo " - verifyLinkRelevance Result: " . ($valid ? 'TRUE (Kept)' : 'FALSE (Should delete)') . "\n";
}

// 2. Search for Better
echo "\nSearching for '$name'...\n";
$newUrl = $service->findGroup($name);
echo " - Found: $newUrl\n";

if ($newUrl && $newUrl !== $badUrl) {
    echo "FIX FOUND! Updating M.Video...\n";
    $company = Company::where('name', 'LIKE', '%M.Video%')->first();
    if ($company) {
        $company->vk_url = $newUrl;
        $company->save();
        echo "Updated DB.\n";
    }
}
