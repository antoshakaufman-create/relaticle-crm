<?php

use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Table: custom_field_values ===\n";
if (Schema::hasTable('custom_field_values')) {
    $columns = Schema::getColumnListing('custom_field_values');
    print_r($columns);
} else {
    echo "Table 'custom_field_values' does not exist.\n";
}
