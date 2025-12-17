<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\People;

$file = 'verified_candidates.json';

if (!file_exists($file)) {
    die("File $file not found. Upload it first.\n");
}

$candidates = json_decode(file_get_contents($file), true);

if (!$candidates) {
    die("No valid data in $file\n");
}

echo "Processing " . count($candidates) . " verified emails...\n";

foreach ($candidates as $c) {
    $email = $c['email'];

    // Filter out False Positives from Holehe summary line
    $validSites = [];
    foreach ($c['verified_sites'] ?? [] as $site) {
        if (str_contains($site, 'Email not used'))
            continue;
        if (str_contains($site, 'Rate limit'))
            continue;
        // Clean up site name (sometimes it has url)
        $validSites[] = trim($site);
    }

    if (empty($validSites)) {
        echo "❌ No valid sites for {$email} (Filtered False Positives)\n";
        continue;
    }

    $sitesStr = implode(', ', $validSites);

    if ($c['type'] === 'person') {
        $person = People::find($c['id']);
        if ($person) {
            $person->email = $email;
            // Store validation proof in notes
            $person->notes = ($person->notes ?? '') . "\n[Verified by Holehe: $sitesStr]";
            $person->save();
            echo "✅ Updated Person: {$person->name} ($email)\n";
        }
    } elseif ($c['type'] === 'company') {
        // Create generic contact if not exists
        $exists = People::where('company_id', $c['id'])->where('email', $email)->exists();
        if (!$exists) {
            $company = Company::find($c['id']);
            if (!$company)
                continue;

            People::create([
                'company_id' => $c['id'],
                'team_id' => $company->team_id ?? 1, // Fix: Inherit team or default
                'name' => 'General Contact',
                'position' => 'Auto-Discovery',
                'email' => $email,
                'notes' => "[Verified by Holehe: $sitesStr]",
                'creation_source' => 'AI_GENERATED'
            ]);
            echo "✅ Created Contact for Company: {$company->name} ($email)\n";
        } else {
            echo "⚠️ Skipped duplicate for {$c['company']}\n";
        }
    }
}

echo "Done.\n";
