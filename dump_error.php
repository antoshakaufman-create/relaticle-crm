<?php

$logFile = '/var/www/relaticle/storage/logs/laravel.log';
if (!file_exists($logFile)) {
    echo "No log file.\n";
    exit;
}

$lines = file($logFile);
$count = count($lines);
$found = false;

// Search backwards for "local.ERROR"
for ($i = $count - 1; $i >= 0; $i--) {
    if (str_contains($lines[$i], 'local.ERROR')) {
        // Print this line and next 20 lines
        echo "Found Error at line $i:\n";
        for ($j = $i; $j < min($i + 30, $count); $j++) {
            echo $lines[$j];
        }
        $found = true;
        break;
        // Only verify the LAST error
    }
}

if (!$found)
    echo "No local.ERROR found in last lines.\n";
