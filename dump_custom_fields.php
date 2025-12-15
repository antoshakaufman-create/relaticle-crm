<?php

use Relaticle\CustomFields\Models\CustomField;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Listing Custom Fields for App\\Models\\Note:\n";

$fields = CustomField::where('entity_type', 'App\\Models\\Note')->get();

foreach ($fields as $field) {
    echo " - Code: {$field->code} | Type: {$field->type} | Label: {$field->name}\n";
}
