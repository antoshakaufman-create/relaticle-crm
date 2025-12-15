<?php

use App\Models\Company;
use App\Models\Note;
use App\Jobs\PerformSmmAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyName = 'M.Video'; // Or PIK Group
$company = Company::where('name', $companyName)->first();

if (!$company) {
    die("Company '$companyName' not found.\n");
}

echo "=== User Story Simulation: SMM Analysis for {$company->name} ===\n";
echo "1. Checking initial Notes count: " . $company->notes()->count() . "\n";

// Inline Logic Test (Bypass Queue)
echo "2. Executing Note Creation Logic (Inline)...\n";

$date = now()->format('Y-m-d H:i');
$noteTitle = "Debug Note Simulation [$date]";
try {
    DB::beginTransaction();

    $note = new Note();
    $note->title = $noteTitle;
    $note->team_id = $company->team_id;
    $note->creator_id = $company->creator_id; // Simulating creator
    $note->save();

    echo " [OK] Note Saved. ID: {$note->id}\n";

    $note->companies()->attach($company->id);
    echo " [OK] Attached to Company via Noteables.\n";

    $bodyContent = "Debug Content from Simulation";
    DB::table('custom_field_values')->insert([
        'tenant_id' => $company->team_id,
        'entity_type' => Note::class,
        'entity_id' => $note->id,
        'custom_field_id' => 7,
        'custom_field_id' => 7,
        'text_value' => $bodyContent,
    ]);
    echo " [OK] Custom Field Inserted.\n";

    DB::commit();
    echo " [SUCCESS] Transaction Committed.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo " [ERROR] Logic Failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Verification
echo "3. Verifying DB Records...\n";
$dbNote = DB::table('notes')->where('id', $note->id)->first();
echo " - DB Note Exists: " . ($dbNote ? 'YES' : 'NO') . "\n";

$dbPivot = DB::table('noteables')->where('note_id', $note->id)->first();
echo " - Pivot Exists: " . ($dbPivot ? 'YES' : 'NO') . "\n";

$dbCF = DB::table('custom_field_values')->where('entity_id', $note->id)->where('custom_field_id', 7)->first();
echo " - Custom Field Values: " . ($dbCF ? 'YES' : 'NO') . "\n";

echo "Simulation Complete.\n";
