<?php
/**
 * Add Score Legend to Notes
 * 
 * Appends a short explanation (legend) of the Lead Score ranges 
 * to the Notes field of every contact that has a score.
 * 
 * Run: php8.5 /tmp/add_score_legend.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;

echo "=== Adding Lead Score Legend to Notes ===\n\n";

$contacts = People::whereNotNull('lead_score')->get();

$legend = "\n--- ℹ️ Расшифровка Score ---\n" .
    "75-100: 🔥 Горячий (Срочно)\n" .
    "50-74:  🟢 Теплый (Оптимизация)\n" .
    "25-49:  🟡 Тепло-холодный (Внедрение)\n" .
    "0-24:   ⚪ Холодный (Низкий приоритет)";

$updated = 0;

foreach ($contacts as $contact) {
    $notes = $contact->notes ?? '';

    // Check if legend already exists
    if (strpos($notes, '--- ℹ️ Расшифровка Score ---') !== false) {
        continue;
    }

    // Append legend after the Score block
    // Find end of Score block (it usually ends before Next block or at end of string)
    // We'll just append it after the "GPT Intent..." line or at the end of the score section.

    if (preg_match('/=== 🎯 LEAD SCORE:.*?(?=(\n\n===|$))/s', $notes, $m)) {
        // found the score block
        $scoreBlock = $m[0];
        $newScoreBlock = $scoreBlock . $legend;

        $newNotes = str_replace($scoreBlock, $newScoreBlock, $notes);

        $contact->update(['notes' => $newNotes]);
        $updated++;
        echo ".";
    }
}

echo "\n\nUpdated $updated contacts with Legend.\n";
