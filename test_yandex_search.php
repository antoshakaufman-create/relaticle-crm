<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Http;

echo "Testing Yandex Search API...\n";

$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id'); // Some search APIs need folder
$searchUrl = config('ai.yandex.search_url', 'https://search.api.cloud.yandex.net/search/v2/search');

if (!$apiKey) {
    echo "No API Key found.\n";
    exit(1);
}

// Format depends on specific Yandex Search API version (Cloud vs XML).
// Trying Yandex Cloud Search API format (common with folder_id)

echo "URL: $searchUrl\n";

try {
    $response = Http::withHeaders([
        'Authorization' => "Api-Key $apiKey",
    ])->post($searchUrl, [
                'folderId' => $folderId,
                'query' => 'Р-Фарм ИНН',
                'searchOptions' => [
                    'type' => 'WEB',
                    'sort' => 'RELEVANCE',
                    'page' => 0,
                ],
            ]);

    echo "Status: " . $response->status() . "\n";
    echo "Body: " . substr($response->body(), 0, 500) . "...\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
