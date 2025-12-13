#!/bin/bash

cd /var/www/relaticle

echo "=== DELETE ALL PEOPLE ==="
php8.5 artisan tinker --execute="
DB::statement('DELETE FROM people');
echo 'Deleted all people';
"

echo ""
echo "=== IMPORT FROM companies_clean.csv ==="
php8.5 artisan tinker --execute="
use App\Models\People;
use App\Models\Team;

\$team = Team::first();
\$csv = array_map('str_getcsv', file('/var/www/relaticle/companies_clean.csv'));
\$headers = array_shift(\$csv);

\$count = 0;
\$skipped = 0;

foreach (\$csv as \$row) {
    if (count(\$row) < 2) { \$skipped++; continue; }
    
    \$company = trim(\$row[0] ?? '');
    \$industry = trim(\$row[1] ?? '');
    \$contact = trim(\$row[2] ?? '');
    \$phone = trim(\$row[3] ?? '');
    \$email = trim(\$row[4] ?? '');
    \$website = trim(\$row[5] ?? '');
    \$services = trim(\$row[7] ?? '');
    \$comments = trim(\$row[8] ?? '');
    
    if (empty(\$company)) { \$skipped++; continue; }
    
    // Parse contact: Name(Position)
    \$contactName = \$contact;
    \$position = '';
    if (preg_match('/^([^(]+)\\(([^)]+)\\)/', \$contact, \$m)) {
        \$contactName = trim(\$m[1]);
        \$position = trim(\$m[2]);
    }
    
    // Clean email
    \$email = preg_replace('/@ru\$/', '.ru', \$email);
    \$email = preg_replace('/;.*/', '', \$email);
    \$email = trim(\$email);
    if (!\$email || !filter_var(\$email, FILTER_VALIDATE_EMAIL)) {
        \$email = null;
    }
    
    // Clean phone
    \$phone = preg_replace('/[^0-9+]/', '', \$phone);
    if (strlen(\$phone) < 7) \$phone = null;
    
    // Clean website
    \$website = preg_replace('/^https?:\\/\\//', '', \$website);
    \$website = trim(\$website, '/');
    
    // Build notes
    \$notes = implode(\"\\n\", array_filter([
        \"Компания: {\$company}\",
        \$services ? \"Услуги: {\$services}\" : null,
        \$comments ? \"Комментарии: {\$comments}\" : null,
    ]));
    
    // Name: use contact name or company
    \$name = \$contactName ?: \$company;
    
    try {
        People::create([
            'name' => \$name,
            'email' => \$email,
            'phone' => \$phone,
            'position' => \$position ?: null,
            'industry' => \$industry ?: null,
            'website' => \$website ?: null,
            'source' => 'XLSX Компании',
            'notes' => \$notes,
            'team_id' => \$team->id,
        ]);
        \$count++;
    } catch(\\Exception \$e) {
        echo \"Error: \" . \$e->getMessage() . \"\\n\";
        \$skipped++;
    }
}
echo \"Imported from Компании: {\$count} (skipped: {\$skipped})\\n\";
"

echo ""
echo "=== IMPORT FROM it_clean.csv ==="
php8.5 artisan tinker --execute="
use App\Models\People;
use App\Models\Team;

\$team = Team::first();
\$csv = array_map('str_getcsv', file('/var/www/relaticle/it_clean.csv'));
\$headers = array_shift(\$csv);

\$count = 0;

foreach (\$csv as \$row) {
    if (count(\$row) < 2) continue;
    
    \$company = trim(\$row[0] ?? '');
    \$website = trim(\$row[1] ?? '');
    \$phone = trim(\$row[2] ?? '');
    \$contact = trim(\$row[3] ?? '');
    \$email = trim(\$row[4] ?? '');
    \$comments = trim(\$row[5] ?? '');
    
    if (empty(\$company)) continue;
    
    // Parse contact
    \$contactName = \$contact;
    \$position = '';
    // Try: Name должность or Name (должность)
    if (preg_match('/^([А-Яа-яA-Za-z\\s]+)(глава|директор|head|manager|руководитель)/ui', \$contact, \$m)) {
        \$contactName = trim(\$m[1]);
        \$position = trim(substr(\$contact, strlen(\$m[1])));
    }
    
    // Clean email
    \$email = trim(\$email);
    if (!\$email || !filter_var(\$email, FILTER_VALIDATE_EMAIL)) {
        \$email = null;
    }
    
    // Clean phone
    \$phone = preg_replace('/[^0-9+]/', '', \$phone);
    if (strlen(\$phone) < 7) \$phone = null;
    
    \$notes = \"Компания: {\$company}\\n\" . (\$comments ? \"Комментарии: {\$comments}\" : '');
    \$name = \$contactName ?: \$company;
    
    try {
        People::create([
            'name' => \$name,
            'email' => \$email,
            'phone' => \$phone,
            'position' => \$position ?: null,
            'industry' => 'IT',
            'website' => \$website ?: null,
            'source' => 'XLSX IT List',
            'notes' => \$notes,
            'team_id' => \$team->id,
        ]);
        \$count++;
    } catch(\\Exception \$e) {
        echo \"Error: \" . \$e->getMessage() . \"\\n\";
    }
}
echo \"Imported from IT List: {\$count}\\n\";
"

echo ""
echo "=== TOTAL ==="
php8.5 artisan tinker --execute="
echo 'Total: ' . App\\Models\\People::count();
"
