<?php
/**
 * Deep VK Analysis using Official API
 * 
 * 1. Resolve screen names to Group IDs
 * 2. Fetch last 5 posts via wall.get
 * 3. Analyze engagement (likes, views, comments)
 * 4. Use YandexGPT to analyze content strategy based on REAL posts
 * 
 * Run: php8.5 /tmp/deep_vk_api.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use Illuminate\Support\Facades\Http;

$vkToken = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';
$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

echo "=== Deep VK Analysis (API) ===\n\n";

function getVkGroupId($screenName, $token)
{
    try {
        $response = Http::get("https://api.vk.com/method/utils.resolveScreenName", [
            'screen_name' => $screenName,
            'access_token' => $token,
            'v' => '5.131'
        ]);

        $data = $response->json();
        if (isset($data['response']['object_id'])) {
            return $data['response']['object_id']; // group_id
        }
    } catch (\Exception $e) {
    }
    return null;
}

function getWallPosts($ownerId, $token, $count = 5)
{
    // Owner ID for groups must be negative
    $ownerId = '-' . abs($ownerId);

    try {
        $response = Http::get("https://api.vk.com/method/wall.get", [
            'owner_id' => $ownerId,
            'count' => $count,
            'access_token' => $token,
            'v' => '5.131'
        ]);

        $data = $response->json();
        return $data['response']['items'] ?? [];
    } catch (\Exception $e) {
        return [];
    }
}

function analyzeContentStrategy($posts, $companyName, $apiKey, $folderId)
{
    $postsText = "";
    $totalLikes = 0;
    $totalViews = 0;

    foreach ($posts as $i => $post) {
        $text = mb_substr($post['text'] ?? '', 0, 300);
        $date = date('d.m.Y', $post['date']);
        $likes = $post['likes']['count'] ?? 0;
        $views = $post['views']['count'] ?? 0;

        $totalLikes += $likes;
        $totalViews += $views;

        $postsText .= "Пост #" . ($i + 1) . " ($date): $text\n(L: $likes, V: $views)\n\n";
    }

    $avgLikes = count($posts) > 0 ? round($totalLikes / count($posts)) : 0;

    $prompt = "Ты SMM-стратег. Проанализируй последние 5 постов компании '$companyName' ВКонтакте.

=== ПОСТЫ ===
$postsText
===

Дай краткий анализ (4-5 предложений):
1. О чем пишут? (новости, польза, продажи)
2. Как реагирует аудитория? (много/мало лайков - среднее $avgLikes)
3. Что улучшить? (конкретный совет)

Не пиши общие фразы. Используй данные.";

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
                    'maxTokens' => 1000,
                ],
                'messages' => [
                    ['role' => 'user', 'text' => $prompt],
                ],
            ]);

        if ($response->successful()) {
            return $response['result']['alternatives'][0]['message']['text'] ?? null;
        }
    } catch (\Exception $e) {
    }
    return null;
}

// Get unique VK URLs
$contacts = People::whereNotNull('vk_url')->get();
$processedUrls = [];

echo "Contacts with VK: " . count($contacts) . "\n";

$processed = 0;

foreach ($contacts as $contact) {
    $vkUrl = $contact->vk_url;

    // Skip duplicates
    if (isset($processedUrls[$vkUrl])) {
        // Just copy analysis if available
        if ($processedUrls[$vkUrl]) {
            $notes = $contact->notes ?? '';
            $notes = preg_replace('/=== VK Аналитика.*$/us', '', $notes);
            $notes = trim($notes) . "\n\n" . $processedUrls[$vkUrl];
            $contact->update(['notes' => trim($notes)]);
        }
        continue;
    }

    $processed++;

    // Extract screen name
    $path = parse_url($vkUrl, PHP_URL_PATH);
    $screenName = trim(str_replace('/', '', $path));

    if (in_array($screenName, ['wall', 'topic', 'album']))
        continue; // Invalid

    // Get Company Name
    $notes = $contact->notes ?? '';
    if (preg_match('/Компания:\s*([^\n]+)/u', $notes, $m)) {
        $companyName = trim($m[1]);
    } else {
        $companyName = $contact->company->name ?? $contact->name;
    }

    echo "[$processed] $companyName ($screenName)... ";

    // 1. Resolve ID
    $groupId = getVkGroupId($screenName, $vkToken);

    if (!$groupId) {
        echo "Group not found (API)\n";
        $processedUrls[$vkUrl] = null;
        continue;
    }

    // 2. Get Posts
    $posts = getWallPosts($groupId, $vkToken);

    if (empty($posts)) {
        echo "No posts found\n";
        $analysis = "=== VK Аналитика ===\nГруппа найдена, но постов нет или доступ закрыт.";
        $processedUrls[$vkUrl] = $analysis;
    } else {
        echo "Found " . count($posts) . " posts. Analyzing... ";

        // 3. Analyze
        $gptAnalysis = analyzeContentStrategy($posts, $companyName, $apiKey, $folderId);

        if ($gptAnalysis) {
            echo "OK\n";
            $analysis = "=== VK Аналитика (API Data) ===\n";
            $analysis .= "ID: $groupId\n";
            $analysis .= "Постов проанализировано: " . count($posts) . "\n";
            $analysis .= "Последний пост: " . date('d.m.Y', $posts[0]['date']) . "\n\n";
            $analysis .= $gptAnalysis;

            $processedUrls[$vkUrl] = $analysis;

            // Update contact Notes
            // Remove old VK analysis if any
            $notes = preg_replace('/=== VK Аналитика.*$/us', '', $notes);
            $notes = trim($notes) . "\n\n" . $analysis;
            $contact->update(['notes' => trim($notes)]);
        } else {
            echo "GPT Error\n";
            $processedUrls[$vkUrl] = null;
        }
    }

    // Rate limit (VK: 3 req/sec is safe, we do ~2 req per loop)
    usleep(400000);
}

echo "\n=== Done ===\n";
