<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\People;

// Export all people with valid emails for Mosint enrichment
$people = People::whereNotNull('email')
    ->where('email', '!=', '')
    ->where('email', 'like', '%@%')
    ->get();

echo "Found " . $people->count() . " verified emails.\n";

$export = [];
foreach ($people as $person) {
    // Filter out obvious dummies or non-corporate if needed, but for now take all
    $export[] = [
        'id' => $person->id,
        'name' => $person->name,
        'email' => $person->email,
        'company' => $person->company->name ?? 'Unknown'
    ];
}

file_put_contents('mosint_candidates.json', json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Exported to mosint_candidates.json\n";
