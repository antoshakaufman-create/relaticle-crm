<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\People;

$dataFile = 'mosint_rich_data.json';

if (!file_exists($dataFile)) {
    echo "No results file found.\n";
    exit(0);
}

$results = json_decode(file_get_contents($dataFile), true);
$imported = 0;

foreach ($results as $hit) {
    if (empty($hit['summary']))
        continue;

    $person = People::find($hit['person_id']);
    if ($person) {
        $notes = $person->notes ?? '';
        $enrichment = "[Mosint] " . $hit['summary'];

        if (!str_contains($notes, "[Mosint]")) {
            $person->notes = $notes . "\n" . $enrichment;
            $person->save();
            echo "Updated {$person->name}: $enrichment\n";
            $imported++;
        }

        // Optional: Tag them if Twitter/Spotify found? 
        // Not needed now, notes are fine.
    }
}

echo "Done. Enriched $imported contacts.\n";
