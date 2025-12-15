<?php

use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Table: noteables ===\n";
if (Schema::hasTable('noteables')) {
    $columns = Schema::getColumnListing('noteables');
    print_r($columns);
} else {
    echo "Table 'noteables' does not exist.\n";
}
