#!/bin/bash

echo "=== Update Yandex API Key ==="

# Update in .env
sed -i 's/YANDEX_API_KEY=.*/YANDEX_API_KEY=AQVN0sLeIPDm1Scjl8tAgFx-kOfBdtjg5uCR08ME/' /var/www/relaticle_data/.env

echo "Updated .env"

# Clear config cache
cd /var/www/relaticle
php8.5 artisan config:clear

echo ""
echo "=== Verify new key ==="
grep YANDEX_API_KEY /var/www/relaticle_data/.env

echo ""
echo "=== Test YandexGPT API ==="
php8.5 artisan tinker --execute="
\$apiKey = config('ai.yandex.api_key');
\$folderId = config('ai.yandex.folder_id');

echo 'API Key: ' . substr(\$apiKey, 0, 10) . '...' . \"\\n\";
echo 'Folder ID: ' . \$folderId . \"\\n\";

\$response = \Illuminate\Support\Facades\Http::timeout(30)
    ->withHeaders([
        'Authorization' => 'Api-Key ' . \$apiKey,
        'x-folder-id' => \$folderId,
    ])
    ->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
        'modelUri' => 'gpt://' . \$folderId . '/yandexgpt/latest',
        'completionOptions' => [
            'stream' => false,
            'temperature' => 0.3,
            'maxTokens' => 100,
        ],
        'messages' => [
            ['role' => 'user', 'text' => 'Привет! Скажи одним словом: работает ли API?'],
        ],
    ]);

echo 'HTTP Status: ' . \$response->status() . \"\\n\";
if (\$response->successful()) {
    echo 'Response: ' . json_encode(\$response->json(), JSON_UNESCAPED_UNICODE) . \"\\n\";
} else {
    echo 'Error: ' . \$response->body() . \"\\n\";
}
"
