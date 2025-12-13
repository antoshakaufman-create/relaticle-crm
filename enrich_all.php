<?php

// Run this on server: php8.5 /tmp/enrich_all.php

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use Illuminate\Support\Facades\Http;

$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

function analyzeCompany($companyName, $website, $apiKey, $folderId)
{
    $prompt = "Ты SMM-эксперт. Проанализируй компанию '$companyName' (сайт: $website).

Найди их официальные соц сети и верни ТОЛЬКО JSON без пояснений:
{
  \"vk_url\": \"ссылка на VK или null\",
  \"telegram_url\": \"ссылка на Telegram или null\",
  \"youtube_url\": \"ссылка на YouTube или null\",
  \"smm_analysis\": \"Краткий SMM-анализ: что может предложить агентство (1-2 предложения)\"
}";

    try {
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Api-Key ' . $apiKey,
                'x-folder-id' => $folderId,
            ])
            ->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                'modelUri' => 'gpt://' . $folderId . '/yandexgpt-lite/latest',
                'completionOptions' => [
                    'stream' => false,
                    'temperature' => 0.3,
                    'maxTokens' => 500,
                ],
                'messages' => [
                    ['role' => 'user', 'text' => $prompt],
                ],
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $text = $data['result']['alternatives'][0]['message']['text'] ?? '';

            // Extract JSON from response
            if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                return json_decode($matches[0], true);
            }
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    return null;
}

// Get all contacts without SMM analysis
$contacts = People::whereNull('smm_analysis')
    ->orWhere('smm_analysis', '=', '')
    ->get();

echo "=== Processing " . count($contacts) . " contacts ===\n\n";

$updated = 0;
$errors = 0;

foreach ($contacts as $contact) {
    // Extract company name from notes
    $companyName = '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $contact->notes ?? '', $m)) {
        $companyName = trim($m[1]);
    }
    if (!$companyName)
        $companyName = $contact->name;

    $website = $contact->website ?? 'не указан';

    echo "[$updated/" . count($contacts) . "] $companyName... ";

    $result = analyzeCompany($companyName, $website, $apiKey, $folderId);

    if ($result) {
        $updateData = [];
        if (!empty($result['vk_url']) && $result['vk_url'] !== 'null' && $result['vk_url'] !== null) {
            $updateData['vk_url'] = $result['vk_url'];
        }
        if (!empty($result['telegram_url']) && $result['telegram_url'] !== 'null' && $result['telegram_url'] !== null) {
            $updateData['telegram_url'] = $result['telegram_url'];
        }
        if (!empty($result['youtube_url']) && $result['youtube_url'] !== 'null' && $result['youtube_url'] !== null) {
            $updateData['youtube_url'] = $result['youtube_url'];
        }
        if (!empty($result['smm_analysis'])) {
            $updateData['smm_analysis'] = $result['smm_analysis'];

            // Also append to notes
            $notes = $contact->notes ?? '';
            if (strpos($notes, 'SMM-анализ:') === false) {
                $notes .= "\n\n--- SMM-анализ ---\n" . $result['smm_analysis'];
                $updateData['notes'] = trim($notes);
            }
        }

        if (!empty($updateData)) {
            $contact->update($updateData);
            echo "OK (" . implode(', ', array_keys($updateData)) . ")\n";
            $updated++;
        } else {
            echo "No data\n";
        }
    } else {
        echo "Error\n";
        $errors++;
    }

    // Small delay to avoid rate limiting
    usleep(300000); // 0.3 seconds
}

echo "\n=== Result ===\n";
echo "Updated: $updated\n";
echo "Errors: $errors\n";
echo "Total processed: " . count($contacts) . "\n";
