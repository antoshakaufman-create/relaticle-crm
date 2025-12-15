<?php

use App\Models\People;
use Illuminate\Database\Eloquent\Builder;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contacts = People::where(function (Builder $query) {
    $query->whereNull('linkedin_url')
        ->orWhere('linkedin_url', '');
})
    ->whereHas('company')
    ->with('company')
    ->limit(500)
    ->get();

$export = [];

foreach ($contacts as $p) {
    if (!$p->name || !$p->company)
        continue;

    $export[] = [
        'id' => $p->id,
        'name' => $p->name,
        'company' => $p->company->name,
        'company_linkedin' => $p->company->linkedin_url ?? null
    ];
}

file_put_contents('contacts_export.json', json_encode($export, JSON_PRETTY_PRINT));
echo "Exported " . count($export) . " contacts to contacts_export.json\n";
