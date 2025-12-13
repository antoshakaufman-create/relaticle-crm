<?php
/**
 * Link contacts to companies and run YandexGPT SMM enrichment
 * Run: php8.5 /tmp/link_and_enrich.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

echo "=== Step 1: Link contacts to companies ===\n";

// Get all companies and build lookup
$companies = Company::all();
$companyLookup = [];
foreach ($companies as $company) {
    $companyLookup[mb_strtolower($company->name)] = $company->id;
}

echo "Companies loaded: " . count($companyLookup) . "\n";

// Link contacts
$contacts = People::whereNull('company_id')->get();
$linked = 0;

foreach ($contacts as $contact) {
    // Try to find company in notes
    $notes = $contact->notes ?? '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $match)) {
        $companyName = mb_strtolower(trim($match[1]));
        if (isset($companyLookup[$companyName])) {
            $contact->company_id = $companyLookup[$companyName];
            $contact->save();
            $linked++;
        }
    }
}

echo "Contacts linked to companies: $linked\n\n";

// ===== Step 2: YandexGPT SMM Enrichment =====
echo "=== Step 2: YandexGPT SMM Enrichment ===\n";

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

            if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                return json_decode($matches[0], true);
            }
        }
    } catch (Exception $e) {
        // Silent fail
    }
    return null;
}

// Get contacts without SMM analysis
$contacts = People::where(function ($q) {
    $q->whereNull('smm_analysis')
        ->orWhere('smm_analysis', '=', '');
})->get();

echo "Contacts to enrich: " . count($contacts) . "\n\n";

$updated = 0;
$errors = 0;

foreach ($contacts as $contact) {
    // Extract company name from notes
    $companyName = '';
    $notes = $contact->notes ?? '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $m)) {
        $companyName = trim($m[1]);
    }
    if (!$companyName) {
        $companyName = $contact->company ? $contact->company->name : $contact->name;
    }

    $website = $contact->website ?? 'не указан';

    echo "[$updated] $companyName... ";

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

            // Add SMM analysis to notes
            $notes = $contact->notes ?? '';
            if (strpos($notes, 'SMM-анализ:') === false) {
                $notes .= "\n\n--- SMM-анализ ---\n" . $result['smm_analysis'];
                $updateData['notes'] = trim($notes);
            }
        }

        if (!empty($updateData)) {
            $contact->update($updateData);
            echo "OK\n";
            $updated++;
        } else {
            echo "No data\n";
        }
    } else {
        echo "Error\n";
        $errors++;
    }

    usleep(300000); // 0.3 sec delay
}

echo "\n=== Final Stats ===\n";
echo "Updated with SMM: $updated\n";
echo "Errors: $errors\n";
echo "Total contacts: " . People::count() . "\n";
echo "With SMM analysis: " . People::whereNotNull('smm_analysis')->where('smm_analysis', '!=', '')->count() . "\n";
echo "With company link: " . People::whereNotNull('company_id')->count() . "\n";
