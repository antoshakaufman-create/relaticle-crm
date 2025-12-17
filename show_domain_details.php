<?php
require __DIR__ . '/vendor/autoload.php';

use App\Models\People; // Assuming we can use Eloquent if we boot app, or just use JSON data

$dataFile = 'mosint_rich_data.json';
$data = json_decode(file_get_contents($dataFile), true);

$domainContacts = [];

foreach ($data as $entry) {
    if (empty($entry['email']))
        continue;

    // Check validation
    $hasMx = false;
    $dns = $entry['dns_records'] ?? [];
    foreach ($dns as $rec) {
        $type = $rec['Type'] ?? $rec[0] ?? '';
        if ($type === 'MX') {
            $hasMx = true;
            break;
        }
    }

    if ($hasMx) {
        $parts = explode('@', $entry['email']);
        $domain = strtolower($parts[1]);

        $domainContacts[$domain][] = $entry['email'];
    }
}

// Sort by count
arsort($domainContacts);

// Show top 5
$i = 0;
foreach ($domainContacts as $domain => $emails) {
    if (count($emails) > 1) { // Only show domains with multiple contacts for brevity
        echo "Domain: $domain (" . count($emails) . " contacts)\n";
        foreach ($emails as $email) {
            echo " - $email\n";
        }
        echo "\n";
        $i++;
        if ($i >= 10)
            break;
    }
}
