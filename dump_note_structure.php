<?php

use Relaticle\CustomFields\Models\CustomField;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Table: notes ===\n";
if (Schema::hasTable('notes')) {
    $columns = Schema::getColumnListing('notes');
    print_r($columns);
} else {
    echo "Table 'notes' does not exist.\n";
}

echo "\n=== All Custom Fields ===\n";
$fields = CustomField::all();
foreach ($fields as $field) {
    echo "ID: {$field->id} | Entity: {$field->entity_type} | Code: {$field->code} | Name: {$field->name}\n";
}
