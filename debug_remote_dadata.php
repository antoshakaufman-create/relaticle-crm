<?php

use Illuminate\Support\Facades\Http;
use App\Models\Company;

echo "Debugging DaData connection...\n";

$company = Company::first();
if (!$company) {
    echo "No company found locally.\n";
    exit;
}

echo "Testing for company: {$company->name}\n";

$apiKey = 'd727a93a800dd5572305eb876d66c44c3099813a';

try {
    $response = Http::withHeaders([
        'Authorization' => "Token $apiKey",
        'Accept' => 'application/json',
    ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party', [
                'query' => $company->name,
                'count' => 1
            ]);

    echo "Status: " . $response->status() . "\n";
    echo "Body: " . substr($response->body(), 0, 500) . "...\n";

    $json = $response->json();
    if (empty($json['suggestions'])) {
        echo "Suggestions empty.\n";
    } else {
        echo "Suggestions found! Count: " . count($json['suggestions']) . "\n";
        print_r($json['suggestions'][0]['data']['inn'] ?? 'No INN');
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
