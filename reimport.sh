#!/bin/bash

cd /var/www/relaticle

echo "=== Delete all People ==="
php8.5 artisan tinker --execute="
use App\Models\People;
\$count = People::withTrashed()->count();
DB::statement('DELETE FROM people');
echo \"Deleted {\$count} people\n\";
"

echo ""
echo "=== Import from companies.csv ==="
php8.5 artisan tinker --execute="
use App\Models\People;
use App\Models\Team;

\$team = Team::first();
if(!\$team) {
    echo 'No team found!';
    exit;
}

\$csv = file_get_contents('/var/www/relaticle/companies.csv');
\$lines = explode(\"\n\", \$csv);

\$count = 0;
\$skipped = 0;
\$rowNum = 0;

foreach (\$lines as \$line) {
    \$rowNum++;
    if (empty(trim(\$line))) continue;
    
    \$row = str_getcsv(\$line, ',', '\"', '');
    
    // Skip first 2 rows (empty row + header)
    if (\$rowNum <= 2) {
        continue;
    }
    
    \$company = trim((string)(\$row[0] ?? ''));
    if (empty(\$company) || strlen(\$company) < 2) {
        \$skipped++;
        continue;
    }
    
    \$industry = trim((string)(\$row[1] ?? ''));
    \$contact = trim((string)(\$row[2] ?? ''));
    \$phone = trim((string)(\$row[3] ?? ''));
    \$email = trim((string)(\$row[4] ?? ''));
    \$website = trim((string)(\$row[5] ?? ''));
    \$services = isset(\$row[7]) ? trim((string)\$row[7]) : '';
    \$comments = isset(\$row[8]) ? trim((string)\$row[8]) : '';
    
    // Clean email
    \$email = str_replace([\"'\", '@ru', ' '], ['', '.ru', ''], \$email);
    \$emails = preg_split('/[;,]/', \$email);
    \$email = trim(\$emails[0] ?? '');
    if (!\$email || !filter_var(\$email, FILTER_VALIDATE_EMAIL)) {
        \$email = null;
    }
    
    // Clean phone
    \$phone = preg_replace('/[^0-9+]/', '', \$phone);
    if (strlen(\$phone) < 7) \$phone = null;
    
    // Parse contact name and position
    \$contactName = \$contact;
    \$position = '';
    if (preg_match('/^([^(]+)\\(([^)]+)\\)/', \$contact, \$m)) {
        \$contactName = trim(\$m[1]);
        \$position = trim(\$m[2]);
    }
    
    // Build notes
    \$notes = implode(\"\n\", array_filter([
        \"Компания: {\$company}\",
        \$services ? \"Услуги: {\$services}\" : null,
        \$comments ? \"Комментарии: {\$comments}\" : null,
    ]));
    
    // Name to use
    \$name = \$contactName ?: \$company;
    if (strlen(\$name) > 255) \$name = substr(\$name, 0, 255);
    
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
        echo \"Error for {\$name}: \" . \$e->getMessage() . \"\n\";
        \$skipped++;
    }
}

echo \"Imported from companies.csv: {\$count} contacts (skipped: {\$skipped})\n\";
"

echo ""
echo "=== Import from it_list.csv ==="
php8.5 artisan tinker --execute="
use App\Models\People;
use App\Models\Team;

\$team = Team::first();
\$csv = file_get_contents('/var/www/relaticle/it_list.csv');
\$lines = explode(\"\n\", \$csv);

\$count = 0;
\$skipped = 0;
\$rowNum = 0;

foreach (\$lines as \$line) {
    \$rowNum++;
    if (empty(trim(\$line))) continue;
    
    \$row = str_getcsv(\$line, ',', '\"', '');
    
    // Skip header
    if (\$rowNum <= 1) continue;
    
    \$company = trim((string)(\$row[0] ?? ''));
    if (empty(\$company) || strlen(\$company) < 2) {
        \$skipped++;
        continue;
    }
    
    \$website = trim((string)(\$row[1] ?? ''));
    \$phone = trim((string)(\$row[2] ?? ''));
    \$contact = trim((string)(\$row[3] ?? ''));
    \$email = trim((string)(\$row[4] ?? ''));
    \$comments = isset(\$row[5]) ? trim((string)\$row[5]) : '';
    
    // Clean email
    \$email = str_replace([\"'\", ' '], '', \$email);
    \$emails = preg_split('/[;,]/', \$email);
    \$email = trim(\$emails[0] ?? '');
    if (!\$email || !filter_var(\$email, FILTER_VALIDATE_EMAIL)) {
        \$email = null;
    }
    
    // Clean phone
    \$phone = preg_replace('/[^0-9+]/', '', \$phone);
    if (strlen(\$phone) < 7) \$phone = null;
    
    // Parse contact name and position
    \$contactName = \$contact;
    \$position = '';
    if (preg_match('/^([^(]+)\\s+(глава|директор|head|manager)/ui', \$contact, \$m)) {
        \$contactName = trim(\$m[1]);
        \$position = substr(\$contact, strlen(\$m[1]));
    }
    
    \$notes = \"Компания: {\$company}\n\" . (\$comments ? \"Комментарии: {\$comments}\" : '');
    
    \$name = \$contactName ?: \$company;
    if (strlen(\$name) > 255) \$name = substr(\$name, 0, 255);
    
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
        echo \"Error for {\$name}: \" . \$e->getMessage() . \"\n\";
        \$skipped++;
    }
}

echo \"Imported from it_list.csv: {\$count} contacts (skipped: {\$skipped})\n\";
"

echo ""
echo "=== Total count ==="
php8.5 artisan tinker --execute="
echo 'Total People: ' . App\Models\People::count() . \"\n\";
"

echo ""
echo "=== Done ==="
