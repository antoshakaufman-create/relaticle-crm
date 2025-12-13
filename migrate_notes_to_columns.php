<?php
/**
 * Migrate Data from Notes to Columns
 * 
 * Extracts:
 * - VK_STATUS -> vk_status
 * - LEAD SCORE -> lead_score & category
 * - VISUAL ANALYTICS -> visual_analysis
 * 
 * Run: php8.5 /tmp/migrate_notes_to_columns.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;

echo "=== Migrating Attributes from Notes to Columns ===\n\n";

$contacts = People::where('notes', '!=', '')->get();

$updated = 0;

foreach ($contacts as $contact) {
    $notes = $contact->notes;
    $updates = [];

    // 1. VK Status
    if (preg_match('/VK_STATUS: (.*)/', $notes, $m)) {
        $updates['vk_status'] = trim($m[1]);
    }

    // 2. Lead Score & Category
    // Format: === 🎯 LEAD SCORE: 64.2 (WARM) ===
    if (preg_match('/=== 🎯 LEAD SCORE: ([\d\.]+) \((.*)\) ===/', $notes, $m)) {
        $updates['lead_score'] = floatval($m[1]);
        $updates['lead_category'] = trim($m[2]);
    }

    // 3. Visual Analysis
    // Format: === 🎨 VISUAL ANALYTICS (BY LISA) === ... (end of string or until next sec)
    if (strpos($notes, '=== 🎨 VISUAL ANALYTICS (BY LISA) ===') !== false) {
        $parts = explode('=== 🎨 VISUAL ANALYTICS (BY LISA) ===', $notes);
        $visual = $parts[1];
        // Cut off if there's anything else later (though Lisa was usually last)
        // Just in case, split by next marker if any
        $updates['visual_analysis'] = trim($visual);
    }

    if (!empty($updates)) {
        $contact->update($updates);
        $updated++;
        echo ".";
    }
}

echo "\n\nMigrated $updated contacts.\n";
