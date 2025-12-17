<?php

use App\Services\VkActionService;

echo "=== Testing VK Finder for 'Kion' ===\n";

$service = new VkActionService();

// Simulate the data from DB (ID 249/287)
$query = 'Kion';
$domain = null; // Some had empty website
$legalName = 'ООО "КИОН"';

echo "Query: $query\n";
echo "Legal Name: $legalName\n";

$startTime = microtime(true);
$url = $service->findGroup($query, $domain, $legalName);
$duration = round(microtime(true) - $startTime, 2);

echo "Result: " . ($url ?? 'NULL') . "\n";
echo "Time: {$duration}s\n";

if ($url === 'https://vk.com/kionru') {
    echo "SUCCESS: Found expected group.\n";
} else {
    echo "FAILURE: Found unexpected group.\n";
}
