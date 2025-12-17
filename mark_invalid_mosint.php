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
$invalidCount = 0;
$markedList = [];

foreach ($results as $hit) {
    if (empty($hit['person_id']))
        continue;

    // Check validity logic
    $isValid = false;
    $dns = $hit['dns_records'] ?? [];
    foreach ($dns as $record) {
        if (isset($record['Type']) && strtoupper($record['Type']) === 'MX') {
            $isValid = true;
            break;
        }
    }

    // Also consider empty DNS as invalid
    if (empty($dns)) {
        $isValid = false;
    }

    if (!$isValid) {
        $person = People::find($hit['person_id']);
        if (!$person)
            continue;

        $noteMsg = "[Mosint] ❌ INVALID: No MX Records found.";

        $notes = $person->notes ?? '';

        // Avoid duplicate marking
        if (!str_contains($notes, "[Mosint] ❌ INVALID")) {
            // Prepend for visibility
            $person->notes = $noteMsg . "\n" . $notes;
            $person->save();
            echo "Marked INVALID: {$person->email} ({$person->name})\n";
            $markedList[] = $person->email;
            $invalidCount++;
        }
    }
}

echo "Done. Marked $invalidCount contacts as Invalid.\n";
if ($invalidCount > 0) {
    echo "Summary of Invalid Emails:\n";
    echo implode("\n", $markedList) . "\n";
}
