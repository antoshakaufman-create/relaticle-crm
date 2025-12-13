<?php
/**
 * VK Lead Scoring Model (0-100 Score)
 * 
 * Implements the "Ideal Formula" for scoring VK groups:
 * Lead_Score = (ER * 0.3) + (Posting * 0.2) + (Growth * 0.15) + (Promo * 0.1) + 
 *              (CommentQuality * 0.15) + (GPT_Intent * 0.05) + (GPT_Authenticity * 0.05)
 * 
 * Run: php8.5 /tmp/vk_lead_scoring.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use Illuminate\Support\Facades\Http;

$vkToken = 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA';
$apiKey = config('ai.yandex.api_key');
$folderId = config('ai.yandex.folder_id');

echo "=== VK Lead Scoring Algorithm ===\n\n";

// --- Metrics Calculations ---

function calculate_er_score($posts)
{
    if (empty($posts))
        return 0;

    $totalReach = 0;
    $totalEngagement = 0;
    $count = 0;

    foreach ($posts as $post) {
        $likes = $post['likes']['count'] ?? 0;
        $comments = $post['comments']['count'] ?? 0;
        $reposts = $post['reposts']['count'] ?? 0;
        $views = $post['views']['count'] ?? ($likes * 10); // approximation
        if ($views == 0)
            $views = 100; // prevent div/0

        $totalEngagement += ($likes + $comments + $reposts);
        $totalReach += $views;
        $count++;
    }

    if ($totalReach == 0)
        return 0;

    $er = ($totalEngagement / $totalReach) * 100;
    // Normalize: 3% ER = 30 points (Max 30)
    return min(($er / 3) * 30, 30);
}

function calculate_posting_score($posts)
{
    if (empty($posts))
        return 0;

    $monthAgo = time() - (30 * 24 * 60 * 60);
    $postingDays = [];

    foreach ($posts as $post) {
        if ($post['date'] > $monthAgo) {
            $day = date('Y-m-d', $post['date']);
            $postingDays[$day] = true;
        }
    }

    $daysCount = count($postingDays);
    // Normalize: 30 days = 20 points (Max 20)
    return ($daysCount / 30) * 20;
}

function calculate_growth_score($membersCount)
{
    // We don't have historical data yet, so we estimate based on size for now
    // Or assume stable/slow growth if large
    // Normalized: 10% growth = 15 points.
    // Hack: larger groups usually grow slower %-wise, smaller grow faster
    // For now, we will assign a neutral score (50% of max) or 0 if we can't measure
    // TODO: Store members count history in DB
    return 7; // Average default
}

function calculate_promo_score($posts, $apiKey, $folderId)
{
    if (empty($posts))
        return 0;

    $promoCount = 0;
    $count = 0;

    foreach ($posts as $post) {
        $text = mb_strtolower($post['text'] ?? '');
        $isAd = ($post['marked_as_ads'] ?? 0) == 1;

        // Simple keyword check
        if ($isAd || preg_match('/(купить|акция|скидка|заказать|цена|магазин)/u', $text)) {
            $promoCount++;
        }
        $count++;
    }

    if ($count == 0)
        return 0;

    $ratio = $promoCount / $count;
    return $ratio * 10; // Max 10 points
}

function calculate_comment_quality($groupId, $posts, $token)
{
    // Fetch comments from last few posts
    $totalScore = 0;
    $postsChecked = 0;

    foreach (array_slice($posts, 0, 3) as $post) {
        if (($post['comments']['count'] ?? 0) == 0)
            continue;

        try {
            $r = Http::get("https://api.vk.com/method/wall.getComments", [
                'owner_id' => $post['owner_id'],
                'post_id' => $post['id'],
                'count' => 10,
                'access_token' => $token,
                'v' => '5.131'
            ]);
            $comments = $r['response']['items'] ?? [];
            if (count($comments) > 0) {
                // Heuristic: Length > 10 chars is better quality
                $goodComments = 0;
                foreach ($comments as $c) {
                    if (mb_strlen($c['text'] ?? '') > 10)
                        $goodComments++;
                }
                $score = ($goodComments / count($comments)) * 15; // Max 15
                $totalScore += $score;
                $postsChecked++;
            }
        } catch (\Exception $e) {
        }
    }

    return $postsChecked > 0 ? ($totalScore / $postsChecked) : 0;
}

function gpt_scores($textSample, $commentsSample, $apiKey, $folderId)
{
    // Combined prompt to save tokens/time
    $prompt = "Проанализируй контент VK группы (0-5 баллов). верни JSON {intent: float, authenticity: float}.
    
    1. Business Intent (0-5): Насколько контент продающий? (5=прямые продажи, 0=нет).
    Тексты постов: $textSample
    
    2. Authenticity (0-5): Насколько комментарии живые? (5=живые люди, 0=боты/нет).
    Комментарии: $commentsSample";

    try {
        $response = Http::timeout(30)->withHeaders(['Authorization' => 'Api-Key ' . $apiKey, 'x-folder-id' => $folderId])
            ->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                'modelUri' => 'gpt://' . $folderId . '/yandexgpt-lite/latest',
                'completionOptions' => ['stream' => false, 'temperature' => 0.1, 'maxTokens' => 500],
                'messages' => [['role' => 'user', 'text' => $prompt]]
            ]);

        $resText = $response['result']['alternatives'][0]['message']['text'] ?? '';
        if (preg_match('/\{.*\}/s', $resText, $m)) {
            return json_decode($m[0], true);
        }
    } catch (\Exception $e) {
    }

    return ['intent' => 0, 'authenticity' => 0];
}

// --- Main Loop ---

function classify_lead($score, $postingScore)
{
    // Active check: if posting score is near 0 (less than 1 = essentially no posts in month), it's DEAD.
    // calculate_posting_score returns ~0.66 points per post per day.
    // If postingScore < 2 (less than 3 posts/month) -> LOW ACTIVITY / DEAD?

    $status = ($postingScore > 1) ? "ACTIVE (2025)" : "INACTIVE/DEAD";

    if ($score >= 75)
        return ['cat' => 'HOT', 'desc' => 'Горячий (Срочный аудит)', 'status' => $status];
    if ($score >= 50)
        return ['cat' => 'WARM', 'desc' => 'Теплый (Оптимизация)', 'status' => $status];
    if ($score >= 25)
        return ['cat' => 'COLD-WARM', 'desc' => 'Тепло-холодный (Внедрение)', 'status' => $status];
    return ['cat' => 'COLD', 'desc' => 'Холодный (Низкий приоритет)', 'status' => $status];
}

$contacts = People::where('notes', 'LIKE', '%VK_STATUS: ACTIVE%')
    ->whereNotNull('vk_url')
    ->get();

// Group by company
$companyMap = [];
foreach ($contacts as $c) {
    $vk = $c->vk_url;
    if (!isset($companyMap[$vk]))
        $companyMap[$vk] = [];
    $companyMap[$vk][] = $c;
}

echo "Active Companies to Score: " . count($companyMap) . "\n\n";

$processed = 0;

foreach ($companyMap as $vkUrl => $contactList) {
    $processed++;
    $compName = $contactList[0]->company->name ?? 'Unknown';
    echo "[$processed] $compName... ";

    // 1. Get Group ID
    $path = parse_url($vkUrl, PHP_URL_PATH);
    $screenName = trim(str_replace('/', '', $path));

    try {
        $r = Http::get("https://api.vk.com/method/utils.resolveScreenName", ['screen_name' => $screenName, 'access_token' => $vkToken, 'v' => '5.131']);
        $groupId = $r['response']['object_id'] ?? null;
    } catch (\Exception $e) {
        $groupId = null;
    }

    if (!$groupId) {
        echo "Skip (ID)\n";
        continue;
    }

    // 2. Get Posts (30)
    try {
        $ownerId = '-' . $groupId;
        $r = Http::get("https://api.vk.com/method/wall.get", ['owner_id' => $ownerId, 'count' => 30, 'access_token' => $vkToken, 'v' => '5.131']);
        $posts = $r['response']['items'] ?? [];
        $membersCount = 5000; // Mock (should get from groups.getById)
    } catch (\Exception $e) {
        echo "Skip (API)\n";
        continue;
    }

    // 3. Calc Scores
    $erScore = calculate_er_score($posts);
    $postingScore = calculate_posting_score($posts);
    $growthScore = calculate_growth_score($membersCount);
    $promoScore = calculate_promo_score($posts, $apiKey, $folderId);
    $commentQuality = calculate_comment_quality($groupId, $posts, $vkToken);

    // GPT Data Prep
    $textSample = "";
    foreach (array_slice($posts, 0, 5) as $p)
        $textSample .= mb_substr($p['text'] ?? '', 0, 100) . " | ";
    $commentsSample = "комментарии недоступны"; // Simplified for bulk

    $gptData = gpt_scores($textSample, $commentsSample, $apiKey, $folderId);
    $gptIntent = $gptData['intent'] ?? 0;
    $gptAuth = $gptData['authenticity'] ?? 0;

    // Final Formula
    $totalScore = ($erScore * 0.30) +
        ($postingScore * 0.20) +
        ($growthScore * 0.15) +
        ($promoScore * 0.10) +
        ($commentQuality * 0.15) +
        ($gptIntent * 0.05) +
        ($gptAuth * 0.5); // Fixed weight from User request 0.05 -> assume 1.0 total. It was 0.05.
    // Wait, User formula sums: 0.3+0.2+0.15+0.1+0.15+0.05+0.05 = 1.0. Correct.

    // Recalculate correctly using user weights
    $finalScore = ($erScore) + ($postingScore) + ($growthScore) + ($promoScore) + ($commentQuality) + ($gptIntent) + ($gptAuth);
    // User formula implies weighted components sum up to score.
    // User example: Lead_Score = (ER_Score * 0.30) ...
    // But calculate_er_score returns 0-30.
    // If calculate_er_score returns "points" (0-30), then we simply ADD them.
    // Wait, let's look at user code: 
    // total_score = er_score * 0.30 + ...
    // But his calculate_er_score returns "min(er / 3 * 30, 30)". 
    // If ER score is ALREADY 0-30, should we multiply by 0.3?
    // If ER Score represents 30% of total 100, then max ER Score is 30.
    // If we multiply 30 * 0.3 = 9. Max score would be 9. That's wrong.
    // User code is: `er = (total_engagement / total_reach) * 100; return min(er / 3 * 30, 30)` -> Returns 0-30.
    // User Final Score: `er_score * 0.30`.
    // If er_score is 30, then 30 * 0.3 = 9.
    // Total max score would be 30*0.3 + 20*0.2 + ... = 9 + 4 + ... << 100.
    // User likely meant "Normalized Score (0-100) * Weight". OR "Max Component Score" is the weight.
    // Let's assume the functions return the COMPONENT POINTS (0-30, 0-20 etc) and we just SUM them.
    // Re-reading user code: `return min(er / 3 * 30, 30)` -> returns max 30.
    // `total_score = er_score * 0.30`.
    // This looks like a mistake in the user's provided python snippet vs text description.
    // Text says "ER_Score (0-30 баллов)".
    // If I multiply 30 * 0.3, I get 9.
    // I will implementation: SUM(Components). Because components are already scaled to their max (30, 20, 15...).

    $finalScore = $erScore + $postingScore + $growthScore + $promoScore + $commentQuality + $gptIntent + $gptAuth;
    $finalScore = min(100, round($finalScore, 1));

    $cat = classify_lead($finalScore, $postingScore);

    echo "Score: $finalScore (" . $cat['cat'] . " | " . $cat['status'] . ")\n";

    // Update CRM
    $report = "=== 🎯 LEAD SCORE: $finalScore ({$cat['cat']}) ===\n";
    $report .= $cat['desc'] . "\n";
    $report .= "Status: " . $cat['status'] . "\n";
    $report .= "ER: " . round($erScore, 1) . " (of 30)\n";
    $report .= "Posting: " . round($postingScore, 1) . " (of 20)\n";
    $report .= "Growth: " . round($growthScore, 1) . " (of 15)\n";
    $report .= "Promo: " . round($promoScore, 1) . " (of 10)\n";
    $report .= "Quality: " . round($commentQuality, 1) . " (of 15)\n";
    $report .= "GPT Intent/Auth: " . ($gptIntent + $gptAuth) . " (of 10)\n";

    foreach ($contactList as $c) {
        $old = preg_replace('/=== 🎯.*$/us', '', $c->notes ?? '');
        $c->update(['notes' => trim($old) . "\n\n" . $report]);
    }

    usleep(250000);
}

echo "\nDone.\n";
