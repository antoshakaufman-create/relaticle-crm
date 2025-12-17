<?php

use App\Services\VkActionService;

echo "=== Debugging Ural Him ===\n";

$service = new VkActionService();
$query = 'Урал Хим';
$legalName = 'АО "УРАЛХИММАШ"';
$address = null;

echo "Query: $query\n";
echo "Legal: $legalName\n";

// I can't easily hook into internal scoring without modifying the class to log.
// But I can run findGroup and see if it returns anything.

$url = $service->findGroup($query, null, $legalName, null);
echo "Result: " . ($url ?? 'NULL') . "\n";
