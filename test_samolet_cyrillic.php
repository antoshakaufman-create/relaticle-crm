<?php

use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';
$name = 'Самолет';

echo "Searching for '$name'...\n";

$response = Http::get('https://api.vk.com/method/groups.search', [
    'q' => $name,
    'count' => 5,
    'access_token' => $token,
    'v' => '5.131',
    'sort' => 0
]);

// var_dump($response->json());
$groups = $response['response']['items'] ?? [];
if (empty($groups)) {
    echo "No items found! Raw:\n";
    var_dump($response->json());
}
foreach ($groups as $group) {
    echo "- [{$group['screen_name']}] {$group['name']} (Verified: " . ($group['verified'] ?? 0) . ", Members: " . ($group['members_count'] ?? '?') . ")\n";
}
