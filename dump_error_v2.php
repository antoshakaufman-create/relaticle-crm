<?php
$logConfig = '/var/www/relaticle/config/logging.php';
// just read the log file blindly reverse
$logFile = '/var/www/relaticle/storage/logs/laravel.log';
$handle = fopen($logFile, 'r');
if (!$handle)
    exit;
fseek($handle, 0, SEEK_END);
$pos = ftell($handle);
$buffer = '';
$lines = [];
// Read last 10000 chars
if ($pos > 10000)
    fseek($handle, -10000, SEEK_END);
else
    rewind($handle);

$content = fread($handle, 10000);
// Split by newlines
$lines = explode("\n", $content);

// Find lines containing "production.ERROR" or "local.ERROR" or just "ERROR"
$found = false;
foreach (array_reverse($lines) as $idx => $line) {
    if (str_contains($line, '.ERROR')) {
        echo "Found potential error:\n$line\n";
        // Print context (next few lines in the original order)
        $realIdx = count($lines) - 1 - $idx;
        for ($k = $realIdx; $k < min($realIdx + 10, count($lines)); $k++) {
            echo $lines[$k] . "\n";
        }
        break;
    }
}
