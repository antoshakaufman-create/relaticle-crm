<?php

use App\Models\Company;
use App\Services\VkActionService;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new VkActionService();

// Target specific companies of interest
$targets = ['M.Video', 'PIK Group', 'Samolet'];
if (count($argv) > 1) {
    if ($argv[1] === 'all') {
        $targets = null; // Process all active
    } else {
        $targets = array_slice($argv, 1);
    }
}

$query = Company::query();
if ($targets) {
    $query->whereIn('name', $targets);
} else {
    $query->whereNotNull('vk_url')->where('vk_status', 'active');
}

$companies = $query->get();

echo "Running Analysis Simulation for " . $companies->count() . " companies...\n";

foreach ($companies as $company) {
    echo "\n=== Processing: {$company->name} ===\n";
    $url = $company->vk_url;

    if (!$url) {
        echo " [SKIP] No VK URL found.\n";
        continue;
    }

    echo " VK URL: $url\n";

    // 1. SMM Analysis (Basic)
    echo " > Running SMM Analysis (Basic)...\n";
    try {
        $result = $service->analyzeGroup($url);

        if (isset($result['error'])) {
            echo " [ERROR] SMM Analysis failed: {$result['error']}\n";
        } else {
            echo " [SUCCESS] ER: {$result['er_score']}, Posts: {$result['posts_per_month']}, Score: {$result['lead_score']}, Cat: {$result['lead_category']}\n";
            // Update DB
            $date = now()->format('Y-m-d H:i');
            $newSummary = "### [$date] SMM Basic Analysis (Simulated)\n" . $result['smm_analysis'];
            $oldNotes = $company->notes ?? '';

            $company->update([
                'smm_analysis' => $result['smm_analysis'],
                'vk_status' => $result['vk_status'],
                'er_score' => $result['er_score'],
                'posts_per_month' => $result['posts_per_month'],
                'lead_score' => $result['lead_score'],
                'lead_category' => $result['lead_category'],
                'notes' => trim("$newSummary\n\n$oldNotes")
            ]);
            echo " [SAVED] Updated metrics and notes.\n";
        }
    } catch (\Exception $e) {
        echo " [EXCEPTION] " . $e->getMessage() . "\n";
    }

    // 2. Deep AI Analysis
    echo " > Running Deep AI Analysis...\n";
    try {
        $resultAI = $service->performDeepAnalysis($url);

        if (isset($resultAI['error'])) {
            echo " [ERROR] AI Analysis failed: {$resultAI['error']}\n";
        } else {
            echo " [SUCCESS] AI Summary generated (" . strlen($resultAI['smm_analysis']) . " chars).\n";
            // Update DB
            $date = now()->format('Y-m-d H:i');
            $newSummary = "### [$date] SMM Deep Analysis (AI - Simulated)\n" . $resultAI['smm_analysis'];
            $oldNotes = $company->notes ?? '';

            $company->update([
                'smm_analysis' => $resultAI['smm_analysis'], // Overwrite or append? UI overwrites 'smm_analysis' field but appends to Notes.
                'notes' => trim("$newSummary\n\n$oldNotes")
            ]);
            echo " [SAVED] Updated analysis field and notes.\n";
        }
    } catch (\Exception $e) {
        echo " [EXCEPTION] " . $e->getMessage() . "\n";
    }

    // Sleep to respect API limits if batching
    usleep(500000);
}

echo "\nSimulation Complete.\n";
