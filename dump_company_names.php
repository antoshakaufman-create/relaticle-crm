<?php

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$names = Company::pluck('name')->toArray();
file_put_contents('existing_company_names.json', json_encode($names, JSON_PRETTY_PRINT));
echo "Exported " . count($names) . " company names.\n";
