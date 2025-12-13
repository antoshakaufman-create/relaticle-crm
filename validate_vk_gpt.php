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

// function signature updated to take industry and context
function validateVKWithGPT($companyName, $vkUrl, $industry, $position, $apiKey, $folderId)
{
    $prompt = "Ты эксперт по соц сетям и бизнесу. 
Проверь, является ли указанная страница VK официальной страницей компании '$companyName'.

Контекст:
- Компания: $companyName
- Отрасль: $industry
- Должность контакта в этой компании: $position
- Ссылка VK: $vkUrl

Внимательно проверь соответствие ОТРАСЛИ. Частая ошибка:
- 'Everest' (Фарма/IT) путают с 'Everest' (Школа/Курсы/Туризм).
- 'Vertex' (Фармзавод) с 'Vertex' (Строители).

Ответь на вопросы:
1. Соответствует ли контент страницы VK указанной отрасли компании ($industry)? (да/нет)
2. Это ОФИЦИАЛЬНАЯ страница именно этой компании? (да/нет/неизвестно)
3. Это страница другой компании с похожим названием? (да/нет)

Ответь СТРОГО в JSON:
{
  \"match_industry\": true/false,
  \"is_official\": true/false,
  \"is_different_company\": true/false,
  \"confidence\": \"high/medium/low\",
  \"reason\": \"краткое пояснение (например: Это школа, а не фарма)\"
}
";

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
                    'temperature' => 0.1, // Lower temp for stricter logic
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

    // Extract info
    $notes = $contact->notes ?? '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $m)) {
        $companyName = trim($m[1]);
    } elseif ($contact->company) {
        $companyName = $contact->company->name;
    } else {
        $companyName = $contact->name; // Fallback
    }

    $industry = $contact->industry ?? $contact->company?->industry ?? 'Фармацевтика/Медицина'; // Default context
    $position = $contact->position ?? 'Сотрудник';

    // Heuristics for industry if empty
    if (!$contact->industry && !$contact->company?->industry) {
        if (stripos($notes, 'фарм') !== false || stripos($companyName, 'pharm') !== false) {
            $industry = 'Фармацевтика';
        }
    }

    echo "[$processed] $companyName ($industry)\n";
    echo "    VK: $vkUrl\n";

    $result = validateVKWithGPT($companyName, $vkUrl, $industry, $position, $apiKey, $folderId);

    if (!$result) {
        echo "    ⚠ GPT ERROR (keeping)\n\n";
        $errors++;
        $seen[$vkUrl] = true;
        continue;
    }

    $matchIndustry = $result['match_industry'] ?? false;
    $isOfficial = $result['is_official'] ?? false;
    $isDifferent = $result['is_different_company'] ?? false;
    $reason = $result['reason'] ?? '';

    // Logic: Keep ONLY if it matches industry AND is official/relevant
    // remove if it is a different company or mismatches industry
    $keepLink = $matchIndustry && !$isDifferent && $isOfficial;

    if ($keepLink) {
        echo "    ✓ VALID\n";
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
