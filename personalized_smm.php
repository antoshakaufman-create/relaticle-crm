<?php
/**
 * Personalized Brand SMM Analysis
 * 
 * Multi-step approach:
 * 1. Find companies WITH social media
 * 2. Study the brand (industry, size, products)
 * 3. Analyze their social media presence
 * 4. Give personalized recommendations based on industry specifics
 * 
 * Run: php8.5 /tmp/personalized_smm.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

function callYandexGPT($prompt, $apiKey, $folderId, $maxTokens = 2000)
{
    try {
        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'Api-Key ' . $apiKey,
                'x-folder-id' => $folderId,
            ])
            ->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                'modelUri' => 'gpt://' . $folderId . '/yandexgpt-lite/latest',
                'completionOptions' => [
                    'stream' => false,
                    'temperature' => 0.5,
                    'maxTokens' => $maxTokens,
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
        echo "(GPT Error: " . $e->getMessage() . ") ";
    }
    return null;
}

echo "=== Personalized Brand SMM Analysis ===\n\n";

// Get contacts WITH social media (VK or Telegram)
$contacts = People::where(function ($q) {
    $q->whereNotNull('vk_url')
        ->orWhereNotNull('telegram_url')
        ->orWhereNotNull('youtube_url');
})->get();

// Group by company
$companyContacts = [];
foreach ($contacts as $contact) {
    $companyName = '';
    $notes = $contact->notes ?? '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $m)) {
        $companyName = trim($m[1]);
    } elseif ($contact->company) {
        $companyName = $contact->company->name;
    }

    if (!$companyName || strlen($companyName) < 3)
        continue;

    $key = $companyName;
    if (!isset($companyContacts[$key])) {
        $companyContacts[$key] = [
            'contacts' => [],
            'vk' => null,
            'telegram' => null,
            'youtube' => null,
            'website' => null,
            'industry' => null,
        ];
    }

    $companyContacts[$key]['contacts'][] = $contact;
    if ($contact->vk_url)
        $companyContacts[$key]['vk'] = $contact->vk_url;
    if ($contact->telegram_url)
        $companyContacts[$key]['telegram'] = $contact->telegram_url;
    if ($contact->youtube_url)
        $companyContacts[$key]['youtube'] = $contact->youtube_url;
    if ($contact->website)
        $companyContacts[$key]['website'] = $contact->website;
    if ($contact->industry)
        $companyContacts[$key]['industry'] = $contact->industry;
}

echo "Companies with social media: " . count($companyContacts) . "\n\n";

$processed = 0;

foreach ($companyContacts as $companyName => $data) {
    $processed++;

    $vk = $data['vk'];
    $tg = $data['telegram'];
    $yt = $data['youtube'];
    $website = $data['website'] ?? 'не указан';
    $industry = $data['industry'] ?? 'не указана';

    echo "[$processed/" . count($companyContacts) . "] $companyName\n";
    echo "    Industry: $industry | VK: " . ($vk ? '✓' : '-') . " | TG: " . ($tg ? '✓' : '-') . " | YT: " . ($yt ? '✓' : '-') . "\n";

    // Step 1: Study the brand
    $studyPrompt = "Ты маркетинговый аналитик. Изучи бренд '$companyName'.

Отрасль: $industry
Сайт: $website

Ответь на вопросы (если чего-то не знаешь - найди информацию сам):

1. Чем занимается компания? (основные продукты/услуги)
2. Целевая аудитория? (B2B/B2C/оба)
3. Есть ли у таких компаний обычно своя маркетинг-команда?
4. Какие проблемы с контентом типичны для этой отрасли?

Формат: краткие ответы, по 1 предложению на вопрос.";

    echo "    Studying brand... ";
    $brandInfo = callYandexGPT($studyPrompt, $apiKey, $folderId, 800);

    if (!$brandInfo) {
        echo "ERROR\n\n";
        continue;
    }
    echo "OK\n";

    // Step 2: Personalized SMM recommendations
    $socialList = [];
    if ($vk)
        $socialList[] = "VK: $vk";
    if ($tg)
        $socialList[] = "Telegram: $tg";
    if ($yt)
        $socialList[] = "YouTube: $yt";

    $recommendPrompt = "Ты SMM-консультант с 20-летним опытом. Клиент - '$companyName'.

=== Информация о бренде ===
$brandInfo

=== Соц сети клиента ===
" . implode("\n", $socialList) . "

=== Твоя задача ===
Дай ПЕРСОНАЛИЗИРОВАННЫЕ рекомендации для ЭТОЙ КОНКРЕТНОЙ компании:

1. **Оценка их соц сетей** - что они делают хорошо/плохо (2 предложения)

2. **Проблемы отрасли** - почему компаниям в их отрасли сложно вести соц сети? Например:
   - Фарма: нет своей маркетинг-команды, мало визуального контента
   - IT: сложно объяснить продукт простым языком
   - Банки: много регуляций, формальный контент

3. **Наши услуги для них** - конкретно что мы можем предложить:
   - Генерация контента с помощью ИИ (визуал, тексты, видео)
   - Ведение соц сетей
   - Таргетированная реклама
   - Чат-боты

Формат: 4-6 предложений, конкретно, без воды. Упоминай название компании.";

    echo "    Creating personalized recommendations... ";
    $recommendations = callYandexGPT($recommendPrompt, $apiKey, $folderId, 1500);

    if (!$recommendations) {
        echo "ERROR\n\n";
        continue;
    }
    echo "OK\n";

    // Combine analysis
    $fullAnalysis = "=== Анализ бренда: $companyName ===\n\n";
    $fullAnalysis .= "📊 Изучение бренда:\n$brandInfo\n\n";
    $fullAnalysis .= "🎯 Персонализированные рекомендации:\n$recommendations";

    // Update all contacts for this company
    foreach ($data['contacts'] as $contact) {
        $contact->update(['smm_analysis' => $fullAnalysis]);

        // Update notes
        $notes = $contact->notes ?? '';
        // Remove old SMM analysis
        $notes = preg_replace('/=== SMM.*$/us', '', $notes);
        $notes = preg_replace('/--- SMM.*$/us', '', $notes);
        $notes = trim($notes) . "\n\n" . $fullAnalysis;
        $contact->update(['notes' => trim($notes)]);
    }

    echo "    Updated " . count($data['contacts']) . " contact(s)\n\n";

    usleep(800000); // 0.8 sec delay
}

echo "\n=== Complete ===\n";
echo "Processed: $processed companies\n";
echo "Contacts with personalized SMM: " . People::whereNotNull('smm_analysis')->where('smm_analysis', 'like', '%Анализ бренда%')->count() . "\n";
