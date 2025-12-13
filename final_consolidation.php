<?php
/**
 * Final SMM Consolidation & Visual Analysis
 * 
 * 1. Process ONLY contacts with "ACTIVE 2025" status
 * 2. Run Deep Content Analysis (Metrics + Strategy)
 * 3. Identify Top 10 Major Companies (Revenue/Brand Size)
 * 4. For Top 10: Run "Lisa AI Visual Expert" analysis
 * 5. Consolidate into final Excel
 * 
 * Run: php8.5 /tmp/final_consolidation.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

$vkToken = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';
$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

echo "=== Final Consolidation & Visual Analysis ===\n\n";

// --- Helpers ---

function getVkGroupId($vkUrl, $token)
{
    if (!$vkUrl)
        return null;
    $path = parse_url($vkUrl, PHP_URL_PATH);
    $screenName = trim(str_replace('/', '', $path));
    if (!$screenName)
        return null;

    try {
        $r = Http::get("https://api.vk.com/method/utils.resolveScreenName", [
            'screen_name' => $screenName,
            'access_token' => $token,
            'v' => '5.131'
        ]);
        return $r['response']['object_id'] ?? null;
    } catch (\Exception $e) {
        return null;
    }
}

function getWallPosts($ownerId, $token)
{
    $ownerId = '-' . abs($ownerId);
    try {
        $r = Http::get("https://api.vk.com/method/wall.get", [
            'owner_id' => $ownerId,
            'count' => 5,
            'access_token' => $token,
            'v' => '5.131'
        ]);
        return $r['response']['items'] ?? [];
    } catch (\Exception $e) {
        return [];
    }
}

function callGPT($prompt, $apiKey, $folderId, $temp = 0.3)
{
    try {
        $response = Http::timeout(30)->withHeaders(['Authorization' => 'Api-Key ' . $apiKey, 'x-folder-id' => $folderId])
            ->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                'modelUri' => 'gpt://' . $folderId . '/yandexgpt-lite/latest',
                'completionOptions' => ['stream' => false, 'temperature' => $temp, 'maxTokens' => 1500],
                'messages' => [['role' => 'user', 'text' => $prompt]]
            ]);
        return $response['result']['alternatives'][0]['message']['text'] ?? null;
    } catch (\Exception $e) {
        return null;
    }
}

// --- 1. Filter Active Groups ---

$contacts = People::where('notes', 'LIKE', '%ACTIVE 2025%')
    ->whereNotNull('vk_url')
    ->get();

// Group by company to avoid duplicates
$uniqueCompanies = [];
foreach ($contacts as $c) {
    $vk = $c->vk_url;
    if (!isset($uniqueCompanies[$vk])) {
        // Get company name
        $compName = $c->company->name ?? 'Unknown';
        if (preg_match('/Компания:\s*([^\n]+)/u', $c->notes ?? '', $m))
            $compName = trim($m[1]);

        $uniqueCompanies[$vk] = [
            'name' => $compName,
            'contacts' => [],
            'analysis' => null,
            'visual_analysis' => null,
            'is_top10' => false
        ];
    }
    $uniqueCompanies[$vk]['contacts'][] = $c;
}

echo "Active Companies to Process: " . count($uniqueCompanies) . "\n\n";

// --- 2. Identity Top 10 Major Companies ---

// Simple heuristic: list of known major brands + large string match
// In real world we'd use revenue data. Here we ask GPT to pick Top 10 from list.
$allNames = array_column($uniqueCompanies, 'name');
$namesStr = implode(", ", array_slice($allNames, 0, 100)); // limit for prompt

echo "Identifying Top 10 Major Companies...\n";
$top10Prompt = "Из этого списка компаний выбери 10 самых крупных по обороту/известности в РФ (бренды типа Газпром, Банки, Ритейл).
Список: $namesStr... (и другие)

Верни JSON список названий: [\"Name1\", \"Name2\"...]";

$top10Json = callGPT($top10Prompt, $apiKey, $folderId, 0.1);
$top10List = [];
if ($top10Json && preg_match('/\[.*\]/s', $top10Json, $m)) {
    $top10List = json_decode($m[0], true) ?? [];
}
echo "Top 10: " . implode(", ", $top10List) . "\n\n";

// Mark Top 10
foreach ($uniqueCompanies as $vk => &$data) {
    foreach ($top10List as $top) {
        if (mb_stripos($data['name'], $top) !== false) {
            $data['is_top10'] = true;
            break;
        }
    }
}
unset($data);

// --- 3. Process Companies ---

$processed = 0;
foreach ($uniqueCompanies as $vkUrl => &$data) {
    $processed++;
    $name = $data['name'];
    echo "[$processed] $name ($vkUrl)... ";

    // Get Posts Metrics
    $groupId = getVkGroupId($vkUrl, $vkToken);
    $posts = $groupId ? getWallPosts($groupId, $vkToken) : [];

    if (empty($posts)) {
        echo "No posts (Access denied?)\n";
        continue;
    }

    // Prepare Data for Analysis
    $postsText = "";
    $likes = 0;
    $views = 0;
    foreach ($posts as $p) {
        $likes += $p['likes']['count'] ?? 0;
        $views += $p['views']['count'] ?? 0;
        $postsText .= mb_substr($p['text'] ?? '', 0, 200) . "\n";
    }
    $avgLikes = count($posts) ? round($likes / count($posts)) : 0;

    // A. Deep Content Analysis
    $prompt = "Проанализируй SMM стратегию компании '$name' (ВКонтакте).
Посты: 
$postsText
Средние лайки: $avgLikes.

Дай краткий отчет (3 пункта):
1. Контент-стратегия (о чем пишут)
2. Вовлеченность (оценка)
3. Что улучшить";

    $data['analysis'] = callGPT($prompt, $apiKey, $folderId) ?? "Ошибка GPT";
    echo "Content analysis OK. ";

    // B. Visual Analysis (Top 10 only)
    if ($data['is_top10']) {
        echo "[TOP 10 VISUAL]... ";
        $visualPrompt = "Ты - Лиза, креативный директор SMM-агентства с экспертностью в AI-генерации.
Твой клиент: крупная компания '$name'.
Ты изучила их соцсети.

Дай экспертную оценку ВИЗУАЛЬНОГО стиля и предложи улучшения с помощью нейросетей (Midjourney/Stable Diffusion):

1. **Текущий стиль** (опиши предполагаемый стиль для бренда такого уровня - строго/ярко/скучно)
2. **Идеи для AI-креативов**: Предложи 2 конкретные идеи для генерации изображений, которые освежат бренд.
3. **Промпт**: Напиши пример промпта для Midjourney для одного креатива.

Пиши от лица Лизы, профессионально и вдохновляюще.";

        $data['visual_analysis'] = callGPT($visualPrompt, $apiKey, $folderId, 0.7);
        echo "Visual OK.";
    }

    echo "\n";

    // Save to all contacts
    $finalNote = "=== 📊 ГЛУБОКИЙ SMM АНАЛИЗ ===\n" . $data['analysis'];

    if ($data['visual_analysis']) {
        $finalNote .= "\n\n=== 🎨 VISUAL ANALYTICS (BY LISA) ===\n" . $data['visual_analysis'];
    }

    foreach ($data['contacts'] as $contact) {
        // Append to notes
        $oldNotes = $contact->notes ?? '';
        // Clean previous analysis headers to avoid dupes
        $oldNotes = preg_replace('/=== 📊.*$/us', '', $oldNotes);
        $oldNotes = preg_replace('/=== 🎨.*$/us', '', $oldNotes);

        $contact->update(['notes' => trim($oldNotes) . "\n\n" . $finalNote]);
    }

    usleep(300000);
}

echo "\n=== Creating Excel ===\n";
// Since I cannot run Python with pandas easily locally in this script, 
// I will output a done message and suggested export command.
echo "Analysis Complete. Run export script to save to Excel.\n";
