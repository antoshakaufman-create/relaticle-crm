<?php
/**
 * Advanced SMM Enrichment - Search by Company with Deep Analysis
 * 1. Get unique companies
 * 2. Find social media links via YandexGPT
 * 3. If found - do deep expert SMM analysis
 * 4. Skip if no social media found
 * 
 * Run: php8.5 /tmp/deep_smm_enrich.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

function callYandexGPT($prompt, $apiKey, $folderId)
{
    try {
        $response = Http::timeout(45)
            ->withHeaders([
                'Authorization' => 'Api-Key ' . $apiKey,
                'x-folder-id' => $folderId,
            ])
            ->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                'modelUri' => 'gpt://' . $folderId . '/yandexgpt-lite/latest',
                'completionOptions' => [
                    'stream' => false,
                    'temperature' => 0.4,
                    'maxTokens' => 1500,
                ],
                'messages' => [
                    ['role' => 'user', 'text' => $prompt],
                ],
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['result']['alternatives'][0]['message']['text'] ?? '';
        }
    } catch (Exception $e) {
        // Silent fail
    }
    return null;
}

// Get unique companies from contacts
echo "=== Advanced SMM Enrichment by Company ===\n\n";

// Build list of unique companies from contacts
$contacts = People::all();
$companyContacts = [];

foreach ($contacts as $contact) {
    // Get company name from notes or company relation
    $companyName = '';
    $notes = $contact->notes ?? '';

    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $m)) {
        $companyName = trim($m[1]);
    } elseif ($contact->company) {
        $companyName = $contact->company->name;
    }

    if (!$companyName || strlen($companyName) < 3)
        continue;

    $website = $contact->website ?? '';

    if (!isset($companyContacts[$companyName])) {
        $companyContacts[$companyName] = [
            'website' => $website,
            'contacts' => []
        ];
    }
    $companyContacts[$companyName]['contacts'][] = $contact;
    if (!$companyContacts[$companyName]['website'] && $website) {
        $companyContacts[$companyName]['website'] = $website;
    }
}

echo "Unique companies: " . count($companyContacts) . "\n";
echo "Total contacts: " . count($contacts) . "\n\n";

$processed = 0;
$enriched = 0;
$skipped = 0;

foreach ($companyContacts as $companyName => $data) {
    $processed++;
    $website = $data['website'] ?: 'не указан';

    echo "[$processed/" . count($companyContacts) . "] $companyName... ";

    // Step 1: Find social media links
    $findSocialPrompt = "Найди официальные социальные сети компании '$companyName' (сайт: $website).

Проверь VK, Telegram, YouTube. Верни ТОЛЬКО JSON:
{
  \"vk_url\": \"полная ссылка на VK или null если не найден\",
  \"telegram_url\": \"полная ссылка на Telegram или null\",
  \"youtube_url\": \"полная ссылка на YouTube или null\",
  \"has_social\": true/false
}

ВАЖНО: Если соцсети не найдены, верни has_social: false";

    $findResult = callYandexGPT($findSocialPrompt, $apiKey, $folderId);

    if (!$findResult) {
        echo "GPT error\n";
        $skipped++;
        continue;
    }

    // Parse JSON
    $socialData = null;
    if (preg_match('/\{[\s\S]*\}/', $findResult, $matches)) {
        $socialData = json_decode($matches[0], true);
    }

    if (!$socialData || !isset($socialData['has_social']) || $socialData['has_social'] === false) {
        // Check if any social found
        $hasAny = !empty($socialData['vk_url']) && $socialData['vk_url'] !== 'null' ||
            !empty($socialData['telegram_url']) && $socialData['telegram_url'] !== 'null' ||
            !empty($socialData['youtube_url']) && $socialData['youtube_url'] !== 'null';

        if (!$hasAny) {
            echo "No social media - SKIPPED\n";
            $skipped++;
            continue;
        }
    }

    // Extract social URLs
    $vkUrl = (!empty($socialData['vk_url']) && $socialData['vk_url'] !== 'null') ? $socialData['vk_url'] : null;
    $tgUrl = (!empty($socialData['telegram_url']) && $socialData['telegram_url'] !== 'null') ? $socialData['telegram_url'] : null;
    $ytUrl = (!empty($socialData['youtube_url']) && $socialData['youtube_url'] !== 'null') ? $socialData['youtube_url'] : null;

    // Step 2: Deep SMM Analysis (only if social media found)
    $socialList = [];
    if ($vkUrl)
        $socialList[] = "VK: $vkUrl";
    if ($tgUrl)
        $socialList[] = "Telegram: $tgUrl";
    if ($ytUrl)
        $socialList[] = "YouTube: $ytUrl";

    $deepAnalysisPrompt = "Ты - SMM-менеджер с 20-летним опытом работы. Проанализируй социальные сети компании '$companyName':

" . implode("\n", $socialList) . "

Дай ГЛУБОКИЙ профессиональный анализ:

1. **Оценка присутствия** - на каких платформах присутствует компания
2. **Что улучшить** - конкретные рекомендации (контент, визуал, частота постов, вовлечённость)
3. **Услуги для предложения** - какие SMM-услуги могут помочь этой компании

Формат ответа - краткий, по делу, 4-6 предложений. Без маркетинговой воды.";

    $analysis = callYandexGPT($deepAnalysisPrompt, $apiKey, $folderId);

    if (!$analysis) {
        echo "Analysis error\n";
        $skipped++;
        continue;
    }

    // Update all contacts for this company
    $updateCount = 0;
    foreach ($data['contacts'] as $contact) {
        $updateData = [
            'smm_analysis' => $analysis,
        ];

        if ($vkUrl)
            $updateData['vk_url'] = $vkUrl;
        if ($tgUrl)
            $updateData['telegram_url'] = $tgUrl;
        if ($ytUrl)
            $updateData['youtube_url'] = $ytUrl;

        // Add to notes
        $notes = $contact->notes ?? '';
        if (strpos($notes, '=== SMM Экспертный Анализ ===') === false) {
            $notes .= "\n\n=== SMM Экспертный Анализ ===\n";
            $notes .= "Компания: $companyName\n";
            if ($vkUrl)
                $notes .= "VK: $vkUrl\n";
            if ($tgUrl)
                $notes .= "Telegram: $tgUrl\n";
            if ($ytUrl)
                $notes .= "YouTube: $ytUrl\n";
            $notes .= "\n$analysis";
            $updateData['notes'] = trim($notes);
        }

        $contact->update($updateData);
        $updateCount++;
    }

    $socialFound = [];
    if ($vkUrl)
        $socialFound[] = 'VK';
    if ($tgUrl)
        $socialFound[] = 'TG';
    if ($ytUrl)
        $socialFound[] = 'YT';

    echo "OK (" . implode('+', $socialFound) . ") → $updateCount contacts\n";
    $enriched++;

    usleep(500000); // 0.5 sec delay between companies
}

echo "\n=== Final Stats ===\n";
echo "Companies processed: $processed\n";
echo "Enriched with analysis: $enriched\n";
echo "Skipped (no social media): $skipped\n";
echo "\nTotal contacts with VK: " . People::whereNotNull('vk_url')->count() . "\n";
echo "Total contacts with TG: " . People::whereNotNull('telegram_url')->count() . "\n";
echo "Total contacts with SMM: " . People::whereNotNull('smm_analysis')->where('smm_analysis', '!=', '')->count() . "\n";
