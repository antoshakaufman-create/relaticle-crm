<?php
/**
 * VK Activity Validation using YandexGPT Knowledge
 * 
 * Ask GPT directly about company's VK activity based on its knowledge
 * VK pages require JS so we can't parse HTML directly
 * 
 * Run: php8.5 /tmp/validate_vk_gpt.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use Illuminate\Support\Facades\Http;

$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

echo "=== VK Activity Validation (GPT Knowledge) ===\n";
echo "Date: " . date('Y-m-d') . "\n\n";

function validateVKWithGPT($companyName, $vkUrl, $apiKey, $folderId)
{
    $prompt = "Ты эксперт по соц сетям. Проверь страницу VK компании '$companyName'.

VK URL: $vkUrl

Ответь на вопросы (используй свои знания о этой компании):

1. Существует ли эта страница VK? (да/нет)
2. Это ОФИЦИАЛЬНАЯ страница компании или фейк/фан-страница? (официальная/неофициальная/неизвестно)
3. Компания '$companyName' активна в соц сетях в России? (да/нет/неизвестно)
4. Это крупная компания которая обычно ведёт соц сети? (да/нет)

Ответь СТРОГО в JSON:
{
  \"exists\": true/false,
  \"is_official\": true/false,
  \"is_active_company\": true/false,
  \"is_large_company\": true/false,
  \"confidence\": \"high/medium/low\",
  \"reason\": \"краткое пояснение\"
}

ВАЖНО: Если это известная крупная компания (банк, телеком, ритейл, нефтегаз) - они обычно активно ведут VK.";

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
                    'temperature' => 0.2,
                    'maxTokens' => 500,
                ],
                'messages' => [
                    ['role' => 'user', 'text' => $prompt],
                ],
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $text = $data['result']['alternatives'][0]['message']['text'] ?? '';

            if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                return json_decode($matches[0], true);
            }
        }
    } catch (\Exception $e) {
        return null;
    }
    return null;
}

// Get contacts with VK links
$contacts = People::whereNotNull('vk_url')->get();

echo "Contacts with VK: " . count($contacts) . "\n\n";

$valid = 0;
$invalid = 0;
$errors = 0;

$processed = 0;
$seen = []; // Track already processed VK URLs

foreach ($contacts as $contact) {
    $vkUrl = $contact->vk_url;

    // Skip if already processed this URL
    if (isset($seen[$vkUrl])) {
        // Apply same result
        if (!$seen[$vkUrl]) {
            $contact->update(['vk_url' => null]);
        }
        continue;
    }

    $processed++;
    $companyName = '';

    $notes = $contact->notes ?? '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $m)) {
        $companyName = trim($m[1]);
    } elseif ($contact->company) {
        $companyName = $contact->company->name;
    } else {
        $companyName = $contact->name;
    }

    echo "[$processed] $companyName\n";
    echo "    VK: $vkUrl\n";

    $result = validateVKWithGPT($companyName, $vkUrl, $apiKey, $folderId);

    if (!$result) {
        echo "    ⚠ GPT ERROR (keeping)\n\n";
        $errors++;
        $seen[$vkUrl] = true;
        continue;
    }

    $exists = $result['exists'] ?? false;
    $isOfficial = $result['is_official'] ?? false;
    $isActiveCompany = $result['is_active_company'] ?? false;
    $isLarge = $result['is_large_company'] ?? false;
    $confidence = $result['confidence'] ?? 'low';
    $reason = $result['reason'] ?? '';

    // Logic: Keep if (exists AND official) OR (large company AND exists)
    $keepLink = ($exists && $isOfficial) || ($isLarge && $exists);

    if ($keepLink) {
        echo "    ✓ VALID (official: " . ($isOfficial ? 'yes' : 'no') . ", large: " . ($isLarge ? 'yes' : 'no') . ")\n";
        echo "    Reason: $reason\n";
        $valid++;
        $seen[$vkUrl] = true;
    } else {
        echo "    ✗ INVALID (removing)\n";
        echo "    Reason: $reason\n";
        $contact->update(['vk_url' => null]);
        $invalid++;
        $seen[$vkUrl] = false;
    }

    echo "\n";
    usleep(400000);
}

// Apply to duplicate URLs
foreach ($contacts as $contact) {
    $vkUrl = $contact->vk_url;
    if ($vkUrl && isset($seen[$vkUrl]) && !$seen[$vkUrl]) {
        $contact->update(['vk_url' => null]);
    }
}

echo "\n=== Results ===\n";
echo "Valid VK (kept): $valid\n";
echo "Invalid VK (removed): $invalid\n";
echo "Errors: $errors\n";
echo "\nContacts with valid VK: " . People::whereNotNull('vk_url')->count() . "\n";
