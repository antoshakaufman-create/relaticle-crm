<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\People;

$dataFile = 'mosint_rich_data.json';
if (!file_exists($dataFile)) {
    echo "No mosint data file found.\n";
    exit;
}

$results = json_decode(file_get_contents($dataFile), true);
$updated = 0;

foreach ($results as $hit) {
    if (empty($hit['person_id']))
        continue;

    $person = People::find($hit['person_id']);
    if (!$person)
        continue;

    $saveNeeded = false;

    // 1. IP Organization
    $ipInfo = $hit['ip_info'] ?? [];
    if (!empty($ipInfo['org'])) {
        $org = $ipInfo['org'];
        if ($person->ip_organization !== $org) {
            $person->ip_organization = $org;
            $saveNeeded = true;
        }
    }

    // 2. Twitter
    $hasTwitter = $hit['twitter'] ?? false;
    // We don't have the URL in the richness data, just boolean.
    // But if we had it, we'd save it. For now, we can maybe form a search URL or just leave it blank
    // actually Mosint output might contain the URL in the raw text logs we parsed, but here we just have boolean.
    // Let's store the boolean in osint_data for now, or if true set a placeholder? 
    // "https://twitter.com/search?q=".$person->email 
    // Let's just rely on osint_data for the flag.

    // 3. OSINT Data (Full Dump)
    // We'll save the whole hit as JSON
    $person->osint_data = $hit;
    $saveNeeded = true; // Always update with latest scan data

    if ($saveNeeded) {
        $person->save();
        $updated++;
        echo "Updated {$person->name}\n";
    }
}

echo "Done. Re-imported OSINT data for $updated contacts.\n";
