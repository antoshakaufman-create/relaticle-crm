<?php
/**
 * VK Activity Validation with YandexGPT
 * 
 * 1. Fetch VK page content
 * 2. Use YandexGPT to analyze if there's recent activity (last 3 months)
 * 3. Mark inactive VK links as invalid
 * 
 * Run: php8.5 /tmp/validate_vk_activity.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use Illuminate\Support\Facades\Http;

$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

echo "=== VK Activity Validation with YandexGPT ===\n";
echo "Date: " . date('Y-m-d') . " (checking for activity since " . date('Y-m-d', strtotime('-3 months')) . ")\n\n";

function fetchVKPage($url)
{
    try {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get($url);

        if ($response->successful()) {
            return $response->body();
        }
    } catch (\Exception $e) {
        return null;
    }
    return null;
}

function analyzeVKActivity($vkUrl, $pageContent, $companyName, $apiKey, $folderId)
{
    // Extract text content (remove HTML tags but keep dates)
    $text = strip_tags($pageContent);
    // Keep only first 3000 chars to fit in context
    $text = mb_substr($text, 0, 3000);

    $prompt = "Ты аналитик соц сетей. Проанализируй страницу VK компании '$companyName'.

URL: $vkUrl

Контент страницы (фрагмент):
---
$text
---

Ответь на вопрос: Есть ли на странице ПОСТЫ или АКТИВНОСТЬ за последние 3 месяца (октябрь-декабрь 2024)?

Признаки активности:
- Даты постов (например: '15 ноя', '2 дек', 'вчера', 'сегодня', '5 дней назад')
- Недавние записи с текстом и картинками
- Количество подписчиков/участников

Ответь СТРОГО в формате JSON:
{
  \"is_active\": true/false,
  \"last_activity\": \"дата последней активности или 'неизвестно'\",
  \"subscribers\": \"число подписчиков или 'неизвестно'\",
  \"reason\": \"краткое объяснение (1 предложение)\"
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
        // Silent fail
    }
    return null;
}

// Get contacts with VK links
$contacts = People::whereNotNull('vk_url')->get();

echo "Contacts with VK: " . count($contacts) . "\n\n";

$active = 0;
$inactive = 0;
$errors = 0;

$processed = 0;

foreach ($contacts as $contact) {
    $processed++;
    $vkUrl = $contact->vk_url;
    $companyName = '';

    // Get company name
    $notes = $contact->notes ?? '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $m)) {
        $companyName = trim($m[1]);
    } elseif ($contact->company) {
        $companyName = $contact->company->name;
    } else {
        $companyName = $contact->name;
    }

    echo "[$processed/" . count($contacts) . "] $companyName\n";
    echo "    VK: $vkUrl\n";

    // Fetch VK page
    echo "    Fetching... ";
    $pageContent = fetchVKPage($vkUrl);

    if (!$pageContent) {
        echo "FETCH ERROR (removing)\n\n";
        $contact->update(['vk_url' => null]);
        $errors++;
        continue;
    }
    echo "OK\n";

    // Analyze with GPT
    echo "    Analyzing activity... ";
    $result = analyzeVKActivity($vkUrl, $pageContent, $companyName, $apiKey, $folderId);

    if (!$result) {
        echo "GPT ERROR\n\n";
        $errors++;
        continue;
    }

    $isActive = $result['is_active'] ?? false;
    $lastActivity = $result['last_activity'] ?? 'неизвестно';
    $subscribers = $result['subscribers'] ?? 'неизвестно';
    $reason = $result['reason'] ?? '';

    if ($isActive) {
        echo "✓ ACTIVE\n";
        echo "    Last activity: $lastActivity | Subscribers: $subscribers\n";
        echo "    Reason: $reason\n";
        $active++;
    } else {
        echo "✗ INACTIVE (removing)\n";
        echo "    Last activity: $lastActivity | Reason: $reason\n";
        $contact->update(['vk_url' => null]);
        $inactive++;
    }

    echo "\n";
    usleep(500000); // 0.5 sec delay
}

echo "\n=== VK Activity Validation Results ===\n";
echo "Active (kept): $active\n";
echo "Inactive (removed): $inactive\n";
echo "Errors: $errors\n";
echo "\nContacts with active VK: " . People::whereNotNull('vk_url')->count() . "\n";
