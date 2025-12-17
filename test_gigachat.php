<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Http;

echo "Testing GigaChat API...\n";

$apiKey = config('ai.gigachat.api_key');
$baseUrl = config('ai.gigachat.base_url', 'https://gigachat.devices.sberbank.ru/api/v1');
$model = config('ai.gigachat.model', 'GigaChat-Pro');

if (!$apiKey) {
    echo "No GigaChat API Key found.\n";
    // Check if we can get token. Usually GigaChat needs Auth token exchange first.
    exit(1);
}

// GigaChat usually requires an OAuth flow or direct access token?
// Most implementation use 'Authorization: Basic <ClientSecret>' to get token, then use token.
// Does the config api_key represent the Basic Auth string or the Access Token?
// Usually env('GIGACHAT_API_KEY') is the Base64 client_id:client_secret.

echo "API Key provided (len: " . strlen($apiKey) . ").\n";

// 1. Get Token (assuming apiKey is the Auth credential)
$scope = 'GIGACHAT_API_CORP'; // or GIGACHAT_API_PERS
$reqId = uniqid();

try {
    // Try to get token
    $response = Http::withHeaders([
        'Authorization' => 'Basic ' . $apiKey,
        'RqUID' => $reqId,
        'Content-Type' => 'application/x-www-form-urlencoded',
    ])->withoutVerifying()->post('https://ngw.devices.sberbank.ru:9443/api/v2/oauth', [
                'scope' => $scope,
            ]);

    $token = null;
    if ($response->successful()) {
        $token = $response->json()['access_token'] ?? null;
        echo "Token received.\n";
    } else {
        echo "Token failed with scope $scope. Trying PER...\n";
        // Try personal scope
        $scope = 'GIGACHAT_API_PERS';
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $apiKey,
            'RqUID' => $reqId,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->withoutVerifying()->post('https://ngw.devices.sberbank.ru:9443/api/v2/oauth', [
                    'scope' => $scope,
                ]);
        if ($response->successful()) {
            $token = $response->json()['access_token'] ?? null;
            echo "Token received (PERS).\n";
        } else {
            echo "Token Auth Failed: " . $response->body() . "\n";
            exit;
        }
    }

    if (!$token)
        exit;

    // 2. Chat Completion with Search
    /*
     We want to find legal name for a company.
     Prompt: "Найди в интернете полное юридическое название и ИНН компании "Р-Фарм". Выведи JSON."
    */

    $prompt = "Найди точное юридическое название и ИНН компании 'УниПро' с сайтом unipro.energy";

    $chatRes = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'X-Client-ID' => $reqId,
    ])->withoutVerifying()->post($baseUrl . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                // 'function_call' => 'auto', // Search might be automatic if not disabled? 
                // Docs say update_interval might trigger search?
            ]);

    echo "Chat Status: " . $chatRes->status() . "\n";
    echo "Chat Body: " . $chatRes->body() . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
