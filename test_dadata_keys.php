<?php

$apiKey = 'd727a93a800dd5572305eb876d66c44c3099813a';
// Secret key usually not needed for FindById on Suggests API, but let's check docs/standard
// FindById is part of Suggestions API.

function dadata_request($url, $data, $apiKey)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Token ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "1. Search 'Газпром'...\n";
$suggestUrl = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party";
$res = dadata_request($suggestUrl, ['query' => 'Газпром', 'count' => 1], $apiKey);

if (!empty($res['suggestions'])) {
    $company = $res['suggestions'][0];
    $inn = $company['data']['inn'];
    echo "Found INN: $inn\n";

    echo "2. Find By ID ($inn)...\n";
    $findByIdUrl = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party";
    $details = dadata_request($findByIdUrl, ['query' => $inn], $apiKey);

    if (!empty($details['suggestions'])) {
        $fullData = $details['suggestions'][0]['data'];
        $finance = $fullData['finance'] ?? null;
        if ($finance) {
            echo "Finance Data Found!\n";
            print_r($finance);
        } else {
            echo "Finance Data still NULL. (Plan restriction?)\n";
        }
    }
} else {
    echo "Nothing found.\n";
}
