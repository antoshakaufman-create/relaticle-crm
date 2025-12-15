<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Pending Jobs (jobs table) ===\n";
if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
    $pending = DB::table('jobs')->get();
    echo "Count: " . $pending->count() . "\n";
    foreach ($pending as $job) {
        echo " - ID: {$job->id} | Queue: {$job->queue} | Attempts: {$job->attempts}\n";
    }
} else {
    echo "Table 'jobs' does not exist.\n";
}

echo "\n=== Failed Jobs (last 5) ===\n";
$failed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->take(5)->get();
foreach ($failed as $job) {
    echo "ID: {$job->id} | Failed At: {$job->failed_at}\n";
    echo "Payload: " . substr($job->payload, 0, 100) . "...\n";
    echo "Exception: " . substr($job->exception, 0, 500) . "...\n\n";
}
