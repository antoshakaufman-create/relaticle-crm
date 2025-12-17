<?php
require __DIR__ . '/vendor/autoload.php';

$dataFile = 'mosint_rich_data.json';
if (!file_exists($dataFile)) {
    echo "No data file found.\n";
    exit(1);
}

$data = json_decode(file_get_contents($dataFile), true);
$validDomains = [];

foreach ($data as $entry) {
    if (empty($entry['email']))
        continue;

    // Check if valid (has MX)
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
        if (count($parts) === 2) {
            $domain = strtolower($parts[1]);
            if (!isset($validDomains[$domain])) {
                $validDomains[$domain] = 0;
            }
            $validDomains[$domain]++;
        }
    }
}

arsort($validDomains);

echo "Found " . count($validDomains) . " unique domains with valid MX records:\n\n";
printf("%-30s | %-10s\n", "DOMAIN", "CONTACTS");
echo str_repeat('-', 45) . "\n";

foreach ($validDomains as $domain => $count) {
    printf("%-30s | %-10d\n", $domain, $count);
}
