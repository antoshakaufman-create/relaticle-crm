<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Http;

echo "Testing Routed Yandex Search API...\n";

$apiKey = config('ai.yandex.api_key'); // AQVN...
$folderId = config('ai.yandex.folder_id');

// Use the IP of api.cloud.yandex.net: 84.201.181.26
// But we want to target 'search.api.cloud.yandex.net'
// We hope they share the same gateway IP or range.
// If not, this might fail with 404 or Bad Gateway.

$gatewayIp = '84.201.181.26';
$targetHost = 'search.api.cloud.yandex.net';
// If search API is on a DIFFERENT IP, this won't work.
// But we have no way to find the real IP if DNS is broken.
// Let's try.

echo "Targeting IP: $gatewayIp with Host: $targetHost\n";

try {
    $response = Http::withHeaders([
        'Authorization' => "Api-Key $apiKey",
        'Host' => $targetHost,
    ])->withoutVerifying()
        ->post("https://$gatewayIp/search/v2/search", [
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
