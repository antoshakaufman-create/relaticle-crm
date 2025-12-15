<?php

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companies = Company::whereNotNull('linkedin_url')->pluck('linkedin_url', 'name')->toArray();
file_put_contents('existing_company_urls.json', json_encode($companies, JSON_PRETTY_PRINT));
echo "Exported " . count($companies) . " company URLs.\n";
