#!/bin/bash

cd /var/www/relaticle

echo "=== STEP 1: Move SMM analysis text to Notes ==="
php8.5 artisan tinker --execute="
use App\Models\People;

\$contacts = People::whereNotNull('smm_analysis')
    ->where('smm_analysis', '!=', '')
    ->get();

\$updated = 0;
foreach (\$contacts as \$contact) {
    \$existingNotes = \$contact->notes ?? '';
    \$smmText = \$contact->smm_analysis;
    
    // Add SMM analysis to notes if not already there
    if (strpos(\$existingNotes, 'SMM-анализ:') === false) {
        \$newNotes = \$existingNotes . \"\\n\\n--- SMM-анализ ---\\n\" . \$smmText;
        \$contact->notes = trim(\$newNotes);
        \$contact->save();
        \$updated++;
    }
}
echo \"Moved SMM analysis to Notes for {\$updated} contacts\\n\";
"

echo ""
echo "=== STEP 2: Clean dirty contacts ==="
php8.5 artisan tinker --execute="
use App\Models\People;

// Find contacts with bad data patterns
\$dirty = People::where(function(\$q) {
    // Names that look like corrupted data
    \$q->where('name', 'like', '%Компания:%')
      ->orWhere('name', 'like', '%Адрес:%')
      ->orWhere('name', 'like', '%\"№%')
      ->orWhere('name', 'like', '%\"\"%')
      ->orWhere('name', 'like', '%Телефон:%')
      ->orWhere('name', 'like', '%Источник:%')
      ->orWhere('name', 'REGEXP', '^[0-9]+\$');
})->get();

echo 'Found ' . count(\$dirty) . \" potentially dirty contacts\\n\";

if (count(\$dirty) > 0) {
    echo 'Sample dirty names:' . \"\\n\";
    foreach(\$dirty->take(10) as \$d) {
        echo '  - ' . substr(\$d->name, 0, 50) . \"\\n\";
    }
}
"

echo ""
echo "=== STEP 3: Current stats ==="
php8.5 artisan tinker --execute="
use App\Models\People;

echo 'Total: ' . People::count() . \"\\n\";
echo 'With VK: ' . People::whereNotNull('vk_url')->count() . \"\\n\";
echo 'With SMM: ' . People::whereNotNull('smm_analysis')->where('smm_analysis', '!=', '')->count() . \"\\n\";
echo 'With Notes: ' . People::whereNotNull('notes')->where('notes', '!=', '')->count() . \"\\n\";
"
