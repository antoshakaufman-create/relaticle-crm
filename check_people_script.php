<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$people = App\Models\People::where('creation_source', 'AI_GENERATED')->get();

echo "Total AI Employees: " . $people->count() . PHP_EOL;

foreach ($people as $p) {
    echo "{$p->company->name}: {$p->name} ({$p->position})" . PHP_EOL;
}
