<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\People;

// Target: ALL Companies and ALL People
// User request: "Try YaSeeker for everyone"

$candidates = [];

// 1. ALL Companies
$companies = Company::all();
echo "Found " . $companies->count() . " Companies.\n";

foreach ($companies as $company) {
    // Basic guesses will be handled by python script based on name
    $candidates[] = [
        'type' => 'company',
        'id' => $company->id,
        'name' => $company->name, // Used for slug generation
        'domain' => $company->website ? parse_url($company->website, PHP_URL_HOST) : null
    ];
}

// 2. ALL People
$people = People::with('company')->get();
echo "Found " . $people->count() . " People.\n";

foreach ($people as $person) {
    $emailUsername = null;
    if ($person->email && str_contains($person->email, '@')) {
        $parts = explode('@', $person->email);
        $emailUsername = $parts[0];
    }

    $candidates[] = [
        'type' => 'person',
        'id' => $person->id,
        'name' => $person->name,
        'email_username' => $emailUsername, // High confidence guess if exists
        'company_name' => $person->company->name ?? null
    ];
}

file_put_contents('candidates.json', json_encode($candidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Exported " . count($candidates) . " candidates to candidates.json\n";
