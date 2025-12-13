#!/bin/bash

echo "=== Current Yandex ENV settings ==="
grep -i yandex /var/www/relaticle_data/.env

echo ""
echo "=== Config values in Laravel ==="
cd /var/www/relaticle
php8.5 artisan config:clear > /dev/null 2>&1

php8.5 artisan tinker --execute="
echo 'ai.yandex.api_key: ' . (config('ai.yandex.api_key') ?: 'NOT SET') . \"\\n\";
echo 'ai.yandex.folder_id: ' . (config('ai.yandex.folder_id') ?: 'NOT SET') . \"\\n\";
echo 'ai.yandex.base_url: ' . config('ai.yandex.base_url') . \"\\n\";
echo 'ai.yandex.model: ' . config('ai.yandex.model') . \"\\n\";
"

echo ""
echo "=== Test Yandex Cloud API endpoint ==="
# Try with IAM token format vs API key format
curl -s -X POST "https://llm.api.cloud.yandex.net/foundationModels/v1/completion" \
  -H "Authorization: Api-Key ajetvrtcaq19kpik8cf6" \
  -H "x-folder-id: b1gn3qao39gb9uecn2c2" \
  -H "Content-Type: application/json" \
  -d '{
    "modelUri": "gpt://b1gn3qao39gb9uecn2c2/yandexgpt/latest",
    "completionOptions": {"stream": false, "temperature": 0.3, "maxTokens": 100},
    "messages": [{"role": "user", "text": "Привет"}]
  }' | head -200
