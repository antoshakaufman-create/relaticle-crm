#!/bin/bash

# Test YandexGPT API directly
cd /var/www/relaticle

php8.5 artisan tinker --execute="
use Illuminate\Support\Facades\Http;

\$apiKey = config('ai.yandex.api_key');
\$folderId = config('ai.yandex.folder_id');
\$baseUrl = config('ai.yandex.base_url');

echo \"API Key: \" . (\$apiKey ? substr(\$apiKey, 0, 10) . '...' : 'NOT SET') . \"\\n\";
echo \"Folder ID: \" . (\$folderId ?: 'NOT SET') . \"\\n\";
echo \"Base URL: \" . \$baseUrl . \"\\n\\n\";

if(!\$apiKey || !\$folderId) {
    echo \"ERROR: API Key or Folder ID not configured!\\n\";
    exit;
}

\$prompt = \"Расскажи коротко (2-3 предложения) о фармацевтической компании Р-Фарм в России.\";

\$response = Http::timeout(60)
    ->withHeaders([
        'Authorization' => 'Api-Key ' . \$apiKey,
        'x-folder-id' => \$folderId,
    ])
    ->post(\$baseUrl . '/completion', [
        'modelUri' => \"gpt://{\$folderId}/yandexgpt/latest\",
        'completionOptions' => [
            'stream' => false,
            'temperature' => 0.3,
            'maxTokens' => 1000,
        ],
        'messages' => [
            ['role' => 'user', 'text' => \$prompt],
        ],
    ]);

echo \"HTTP Status: \" . \$response->status() . \"\\n\";
echo \"Response: \" . \$response->body() . \"\\n\";
"
