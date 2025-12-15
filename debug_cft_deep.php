<?php

use App\Services\VkActionService;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new VkActionService();

echo "=== Deep Debug: CFT ===\n";

// 1. Scraping Check
$url = "https://cft.ru";
echo "1. Scraping $url ...\n";
try {
    $response = Http::withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ])->get($url);

    echo "   Status: " . $response->status() . "\n";
    $html = $response->body();
    echo "   HTML Length: " . strlen($html) . "\n";
    preg_match_all('/vk\.com\/([a-zA-Z0-9_.-]+)/i', $html, $matches);
    print_r($matches[1]);
} catch (\Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 2. Full Name Search
$fullName = "Центр Финансовых Технологий";
echo "\n2. Searching VK for '$fullName'...\n";
try {
    $response = Http::get('https://api.vk.com/method/groups.search', [
        'q' => $fullName,
        'count' => 10,
        'access_token' => 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA',
        'v' => '5.131',
        'fields' => 'members_count,verified,site'
    ]);
    foreach ($response['response']['items'] ?? [] as $i) {
        echo " - {$i['screen_name']} (Members: {$i['members_count']}, Site: {$i['site']})\n";
    }
} catch (\Exception $e) {
}


// 3. GPT Keywords
echo "\n3. Asking GPT for keywords...\n";
// Reflection hack to use private finder if I can, otherwise just simulate
// Note: VkActionService doesn't expose GPT public method aside from findGroup internally.
// We'll trust the logic if we see the name search works.
