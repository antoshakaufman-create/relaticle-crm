<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\People;

$total = Company::doesntHave('people')->count();
$withWebsite = Company::doesntHave('people')->whereNotNull('website')->where('website', '!=', '')->count();
$dadataContacts = People::where('creation_source', 'AI_GENERATED')->where('name', 'DaData Contact')->count();

echo "Companies without people: $total\n";
echo "Companies without people BUT WITH WEBSITE: $withWebsite (Prev: 8)\n";
echo "DaData Contacts Created: $dadataContacts\n";
$withInn = Company::whereNotNull('inn')->where('inn', '!=', '')->count();
echo "Companies with INN: $withInn / " . Company::count() . "\n";
