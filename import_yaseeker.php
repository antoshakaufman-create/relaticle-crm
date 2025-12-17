<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\People;

$dataFile = 'yaseeker_global_results.json';

if (!file_exists($dataFile)) {
    echo "No results file found.\n";
    exit(0);
}

$results = json_decode(file_get_contents($dataFile), true);

if (empty($results)) {
    echo "Results file is empty or invalid JSON.\n";
    exit(0);
}

echo "Found " . count($results) . " matches to process.\n";

$imported = 0;

foreach ($results as $hit) {
    echo "Processing hit: {$hit['name']} ({$hit['type']})\n";

    $emails = $hit['found_emails'];
    $source = "YaSeeker Scan ({$hit['username_used']})";

    if ($hit['type'] == 'company') {
        // Update Company Notes or create generic contact?
        $company = Company::find($hit['id']);
        if ($company) {
            $notes = $company->notes ?? '';
            $newNote = "YaSeeker Found Emails: " . implode(', ', $emails);

            if (!str_contains($notes, $newNote)) {
                $company->notes = $notes . "\n" . $newNote;
                $company->save();
                echo "  -> Company notes updated.\n";
                $imported++;
            }

            // Create a "Lead" person?
            // Only if reliable. Usually info@yandex not very useful but better than nothing.
        }
    } elseif ($hit['type'] == 'person') {
        // Update Person
        $person = People::find($hit['id']);
        if ($person) {
            // Check if email is new
            $currentEmail = $person->email;
            $newEmail = $emails[0]; // Take first

            if (empty($currentEmail)) {
                $person->email = $newEmail;
                $person->notes .= "\n[YaSeeker] Found personal email: $newEmail";
                $person->save();
                echo "  -> Person email updated: $newEmail\n";
                $imported++;
            } else {
                $person->notes .= "\n[YaSeeker] Alt email: $newEmail";
                $person->save();
            }
        }
    }
}

echo "Done. Imported/Updated $imported records.\n";
