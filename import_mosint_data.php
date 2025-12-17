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

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Decode Error: " . json_last_error_msg() . "\n";
    exit;
}

echo "Decoded " . count($results) . " entries.\n";

$imported = 0;
$count = 0;

foreach ($results as $hit) {
    $count++;
    if (empty($hit['summary'])) {
        // Fallback: Generate summary from other data
        $summaryParts = [];

        if (!empty($hit['dns_records'])) {
            $mxFound = false;
            foreach ($hit['dns_records'] as $r) {
                if ($r['Type'] == 'MX')
                    $mxFound = true;
            }
            if ($mxFound)
                $summaryParts[] = "DNS: MX Valid";
        }

        if ($hit['twitter'])
            $summaryParts[] = "Twitter: YES";
        if ($hit['spotify'])
            $summaryParts[] = "Spotify: YES";

        if (empty($summaryParts))
            continue; // Still empty? Skip.

        $hit['summary'] = implode(' | ', $summaryParts);
    }

    $person = People::find($hit['person_id']);

    if ($count <= 5) {
        echo "Checking ID: {$hit['person_id']} -> Result: " . ($person ? "FOUND" : "NOT FOUND") . "\n";
    }

    if ($person) {
        // debug
        // echo "Found Person: {$person->id}\n";

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
