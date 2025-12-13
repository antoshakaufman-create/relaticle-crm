<?php

// Run this on server: php8.5 /tmp/import_companies.php

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;

echo "=== Deleting existing contacts ===\n";
People::query()->forceDelete();
echo "Deleted.\n\n";

// Use fgetcsv for proper multi-line handling
$handle = fopen('/tmp/clients_import.csv', 'r');
if (!$handle) {
    die("Cannot open CSV file\n");
}

// Skip header
fgetcsv($handle);

$imported = 0;
$skipped = 0;

while (($fields = fgetcsv($handle)) !== false) {
    if (count($fields) < 2)
        continue;

    $company = trim($fields[0] ?? '');
    $industry = trim($fields[1] ?? '');
    $position = trim($fields[2] ?? '');
    $phone = trim($fields[3] ?? '');
    $email = trim($fields[4] ?? '');
    $website = trim($fields[5] ?? '');
    $services = trim($fields[7] ?? '');
    $comments = trim($fields[8] ?? '');

    if (empty($company) || strlen($company) < 2) {
        $skipped++;
        continue;
    }

    $contactName = $company;
    $contactPosition = '';

    if (!empty($position)) {
        if (preg_match('/^([^(]+)\((.+)\)/', $position, $m)) {
            $contactName = trim($m[1]);
            $contactPosition = trim($m[2]);
        } else {
            $contactName = $position;
        }
    }

    // Clean up newlines in all fields
    $contactName = preg_replace('/\s+/', ' ', $contactName);
    $contactPosition = preg_replace('/\s+/', ' ', $contactPosition);

    $notes = "Компания: " . $company;
    if (!empty($contactPosition))
        $notes .= "\nДолжность: " . $contactPosition;
    if (!empty($services))
        $notes .= "\nУслуги: " . preg_replace('/\s+/', ' ', $services);
    if (!empty($comments))
        $notes .= "\nКомментарии: " . preg_replace('/\s+/', ' ', $comments);

    $email = str_replace("'", '', $email);
    $email = preg_replace('/;.*/', '', $email);
    $email = preg_replace('/\s+/', '', $email);
    $email = trim($email);

    $website = str_replace('"', '', $website);
    $website = preg_replace('/\s+/', '', $website);
    $website = trim($website);
    if (!empty($website) && strpos($website, 'http') !== 0) {
        $website = 'https://' . $website;
    }

    $phone = preg_replace('/\s+/', ' ', $phone);
    $phone = trim($phone);

    try {
        People::create([
            'team_id' => 1,
            'creator_id' => 1,
            'name' => $contactName,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'position' => $contactPosition ?: null,
            'industry' => $industry ?: null,
            'website' => $website ?: null,
            'notes' => $notes,
            'source' => 'CSV Import',
            'creation_source' => 'import',
        ]);
        $imported++;
        echo ".";
    } catch (Exception $e) {
        echo "E";
        $skipped++;
    }
}

fclose($handle);

echo "\n\n=== Result ===\n";
echo "Imported: $imported\n";
echo "Skipped: $skipped\n";
