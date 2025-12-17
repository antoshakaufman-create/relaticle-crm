<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\People;

$dataFile = 'phone_results.json';
if (!file_exists($dataFile)) {
    echo "No phone results file found.\n";
    exit;
}

$results = json_decode(file_get_contents($dataFile), true);
$updated = 0;

foreach ($results as $hit) {
    if (empty($hit['phones']))
        continue;

    $person = People::find($hit['person_id']);
    if (!$person)
        continue;

    $phones = array_unique($hit['phones']);
    $primaryPhone = $phones[0]; // Take first valid match

    // Clean phone for DB? (Optional, but DB might have limits. Taking as string.)

    $notesArg = "[OSINT] Discovered Phones: " . implode(', ', $phones);

    $saveNeeded = false;

    // 1. Update Primary Phone if empty
    if (empty($person->phone)) {
        $person->phone = $primaryPhone;
        $saveNeeded = true;
        echo "Set Phone for {$person->name}: $primaryPhone\n";
    }

    // 2. Add to Notes (avoid duplicates)
    $notes = $person->notes ?? '';
    if (!str_contains($notes, "[OSINT] Discovered Phones")) {
        $person->notes = $notes . "\n" . $notesArg;
        $saveNeeded = true;
    }

    if ($saveNeeded) {
        $person->save();
        $updated++;
    }
}

echo "Done. Updated phones for $updated contacts.\n";
