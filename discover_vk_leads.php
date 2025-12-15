<?php

use App\Services\VkActionService;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new VkActionService();

$queries = [
    'Жилой комплекс Москва',
    'Бренд одежды',
    'Онлайн школа',
    'Производство мебели',
    'Медицинский центр',
    'Автодилер',
    'Фитнес клуб',
    'Салон красоты',
    'Ресторан',
    'HR агентство'
];

$candidates = [];

echo "Searching VK for potential leads...\n";

foreach ($queries as $query) {
    // Search specific groups
    $response = Http::get('https://api.vk.com/method/groups.search', [
        'q' => $query,
        'count' => 3, // Take top 3
        'access_token' => 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA',
        'v' => '5.131',
        'fields' => 'members_count,verified,site'
    ]);

    $groups = $response['response']['items'] ?? [];

    foreach ($groups as $group) {
        $id = $group['screen_name'];
        $name = $group['name'];
        $url = "https://vk.com/$id";

        // Skip if members count is too low (not a serious business)
        if (($group['members_count'] ?? 0) < 1000)
            continue;

        echo " - Analyzing: $name ($url)...\n";

        try {
            // Re-use logic from VkActionService but we can't directly use analyzeGroup easily if it expects clean URL sometimes? 
            // Actually analyzeGroup takes URL.
            $analysis = $service->analyzeGroup($url);

            if (isset($analysis['error']))
                continue;

            $score = $analysis['lead_score'];

            // Filter: We want ACTIVE leads (score > 40 usually implies some activity)
            if ($score > 30) {
                $candidates[] = [
                    'name' => $name,
                    'url' => $url,
                    'industry' => $query,
                    'score' => $score,
                    'category' => $analysis['lead_category'],
                    'er' => $analysis['er_score'],
                    'posts' => $analysis['posts_per_month'],
                    'promo' => isset($analysis['promo_score']) ? $analysis['promo_score'] : ($score - $analysis['er_score'] - $analysis['posts_per_month'] - 7 - 5), // Reverse engineer approx promo if not returned raw
                    'status' => $analysis['vk_status']
                ];
            }
        } catch (\Exception $e) {
            continue;
        }
    }
    sleep(1); // Polite delay
}

// Sort by Score DESC
usort($candidates, function ($a, $b) {
    return $b['score'] <=> $a['score'];
});

// Output top 15
echo "\n=== Top 10 Interesting Companies ===\n";
$count = 0;
foreach ($candidates as $c) {
    if ($count >= 10)
        break;
    echo "{$count}. **{$c['name']}** ({$c['industry']})\n";
    echo "   - URL: {$c['url']}\n";
    echo "   - Score: {$c['score']} ({$c['category']})\n";
    echo "   - Stats: ER {$c['er']} | Posts: {$c['posts']}/mo\n";
    echo "   - Why: {$c['status']}. High activity indicates budget/interest.\n\n";
    $count++;
}
