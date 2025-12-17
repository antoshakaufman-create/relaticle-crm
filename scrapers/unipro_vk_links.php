<?php

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

// Fix DB connection if needed
$dbPath = base_path('database/database.sqlite');
if (!file_exists($dbPath)) {
    echo "Database file not found at $dbPath" . PHP_EOL;
    exit(1);
}
Config::set('database.connections.sqlite.database', $dbPath);

$links = [
    'Березовская ГРЭС' => 'https://vk.com/bgresunipro',
    'Смоленская ГРЭС' => 'https://vk.com/smgres',
    'Сургутская ГРЭС-2' => 'https://vk.com/public193489057',
    'Яйвинская ГРЭС' => 'https://vk.com/ygres',
    'Telegram' => 'https://t.me/unipronrg',
    // Added based on inspection
    'Website' => 'https://www.unipro.energy/',
];

$verifiedLinks = [];

echo "Verifying links..." . PHP_EOL;

foreach ($links as $title => $url) {
    echo "Checking $title ($url)... ";
    try {
        $response = Http::timeout(10)->get($url);
        if ($response->successful() || $response->redirect()) {
            echo "OK" . PHP_EOL;
            $verifiedLinks[] = [
                'title' => $title,
                'url' => $url,
                'status' => 'active',
                'verified_at' => now()->toIso8601String(),
            ];
        } else {
            echo "FAILED (" . $response->status() . ")" . PHP_EOL;
        }
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . PHP_EOL;
    }
}


// Ensure tables exist
if (!Schema::hasTable('companies')) {
    echo "Table 'companies' not found. Running migrations..." . PHP_EOL;
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        echo "Migrations completed." . PHP_EOL;
    } catch (\Exception $e) {
        echo "Migration failed: " . $e->getMessage() . PHP_EOL;
        // Proceeding anyway might fail, but let's try
    }
}

$company = Company::where('name', 'like', '%Unipro%')->first();

if (!$company) {
    echo "Company 'Unipro' not found. Creating a placeholder..." . PHP_EOL;
    $company = new Company();
    $company->name = 'Unipro';
    // Ensure Team exists (default id 1 might not exist)
    $team = \App\Models\Team::first();
    if (!$team) {
        echo "Creating a default Team..." . PHP_EOL;
        $team = new \App\Models\Team(['name' => 'Default Team', 'personal_team' => true]);
        // Create a user for the team
        $user = \App\Models\User::first();
        if (!$user) {
            $user = \App\Models\User::forceCreate([
                'name' => 'Admin',
                'email' => 'admin@localhost',
                'password' => 'password',
            ]);
        }
        $team->user_id = $user->id;
        $team->save();
    }
    $company->team_id = $team->id;
    $company->save();
}

echo "Updating company: " . $company->name . " (ID: " . $company->id . ")" . PHP_EOL;

$currentSmm = $company->smm_analysis ?? [];
$currentSmm['related_links'] = $verifiedLinks;

$company->smm_analysis = $currentSmm;
$company->save();

echo "Done. Links saved." . PHP_EOL;
