<?php

use App\Models\Company;
use App\Models\User;
use App\Services\VkActionService;
use App\Jobs\PerformSmmAnalysis;
use App\Jobs\PerformDeepAiAnalysis;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new VkActionService();
$user = User::first();
$teamId = $user->current_team_id ?? 1;

$queries = [
    'Жилой комплекс Москва',
    'Бренд одежды',
    'Онлайн школа',
    'Автодилер',
    'Фитнес клуб',
    'Производство мебели',
    'Медицинский центр',
    'Ресторан',
    'HR агентство',
    'IT курсы'
];

$imported = [];

echo "=== scanning VK & Importing to CRM ===\n";

foreach ($queries as $query) {
    try {
        $response = Http::get('https://api.vk.com/method/groups.search', [
            'q' => $query,
            'count' => 2, // 2 per category = ~20 leads
            'access_token' => 'vk1.a.33bsbI5XV3fhrWMN1Ut2VYDbXCNTafhZwcBigSq5XKBlckhhuDnbDnf5Q-TQ7e5Fe8iLkCWRQLdlsAJaC7kbjiK4bAEbTOxSd7qnHMuEUsDF-gKW46vOHlWTPEmP6X5qT6tMZffX9tXIt8vz-FBDuL1Yn5G18TYOnqcH3rxMhmHSNdKy0utYvOTHIXy8dDh8tEdhX1ise6KVvLXURkk0gA',
            'v' => '5.131',
            'fields' => 'members_count,verified,site'
        ]);

        $groups = $response['response']['items'] ?? [];

        foreach ($groups as $group) {
            $id = $group['screen_name'];
            $name = $group['name'];
            $vkUrl = "https://vk.com/$id";
            $site = $group['site'] ?? null;

            // Clean site URL
            if ($site && !str_starts_with($site, 'http')) {
                $site = "https://$site";
            }
            if ($site && str_contains($site, 'vk.com')) {
                $site = null; // Don't use VK page as website
            }

            if (($group['members_count'] ?? 0) < 1000)
                continue;

            // Check duplicate
            if (Company::where('vk_url', $vkUrl)->exists()) {
                echo " - Skip existing: $name\n";
                // Add to imported list anyway so we can enrich it
                $c = Company::where('vk_url', $vkUrl)->first();
                $imported[] = [
                    'id' => $c->id,
                    'name' => $c->name,
                    'vk_url' => $vkUrl
                ];
                continue;
            }

            echo " + Creating: $name ($vkUrl)...\n";

            $company = Company::create([
                'name' => $name,
                'vk_url' => $vkUrl,
                'website' => $site,
                'team_id' => $teamId,
                'creator_id' => $user->id,
                'industry' => $query,
                'vk_status' => 'NEW'
            ]);

            // Dispatch SMM Analysis (Basic + Deep)
            PerformSmmAnalysis::dispatch($company);
            PerformDeepAiAnalysis::dispatch($company);

            $imported[] = [
                'id' => $company->id,
                'name' => $company->name,
                'vk_url' => $vkUrl
            ];
        }
    } catch (\Exception $e) {
        echo "Error scanning $query: " . $e->getMessage() . "\n";
    }
}

// Output JSON for Python script
// echo "\nJSON_EXPORT_START\n";
// echo json_encode($imported);
// echo "\nJSON_EXPORT_END\n";
file_put_contents('companies_to_enrich.json', json_encode($imported, JSON_PRETTY_PRINT));
echo "Saved " . count($imported) . " companies to companies_to_enrich.json\n";
