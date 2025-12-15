<?php

$logFile = __DIR__ . '/storage/logs/laravel.log';

if (!file_exists($logFile)) {
    die("Log file not found at $logFile\n");
}

// Read last 200 lines
$lines = array_slice(file($logFile), -200);
$foundError = false;
$output = [];

// Iterate backwards to find the last error block
for ($i = count($lines) - 1; $i >= 0; $i--) {
    $line = $lines[$i];

    // Add line to output buffer (prepend since we go backwards)
    array_unshift($output, $line);

    // If we find the start of an error, we can stop if we have enough context
    if (strpos($line, 'local.ERROR') !== false) {
        // We found the header of the *most recent* error.
        // Let's capture this block plus a few lines before it maybe?
        // Actually, let's just dump the whole tail if it's not too long.
        $foundError = true;
        // Break only if we found an error AND we have collected enough context?
        // Let's just dump the last 50 lines if we found an error, or search explicitly.
        break;
    }
}

// If error found, print the accumulated lines (which is the error block + tail)
if ($foundError) {
    echo "--- LAST ERROR BLOCK ---\n";
    echo implode("", $output);
} else {
    echo "No 'local.ERROR' found in last 200 lines. Dumping last 20 lines:\n";
    echo implode("", array_slice($lines, -20));
}
