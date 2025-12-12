#!/bin/bash

# Import using CSV that we already have
cd /var/www/relaticle

php8.5 artisan tinker --execute="
use App\Models\People;
use App\Models\Team;

\$csv = file_get_contents('/var/www/relaticle/Клиенты-2.csv');
\$lines = explode(\"\\n\", \$csv);

echo \"Found \" . count(\$lines) . \" lines\\n\";

\$team = Team::first();
if(!\$team) {
    echo 'No team found!';
    exit;
}

\$count = 0;
\$skipped = 0;
\$isFirstRow = true;

foreach (\$lines as \$line) {
    if (empty(trim(\$line))) continue;
    
    \$row = str_getcsv(\$line, ',', '\"', '');
    
    if (\$isFirstRow) {
        echo \"Headers: \" . json_encode(\$row, JSON_UNESCAPED_UNICODE) . \"\\n\\n\";
        \$isFirstRow = false;
        continue;
    }
    
    if (count(\$row) < 2) {
        \$skipped++;
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
    if (preg_match('/^([^(]+)\\(([^)]+)\\)/', \$contact, \$m)) {
        \$contactName = trim(\$m[1]);
        \$position = trim(\$m[2]);
    }
    
    // Build notes
    \$notes = implode(\"\\n\", array_filter([
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
            'source' => 'CSV Import',
            'notes' => \$notes,
            'team_id' => \$team->id,
        ]);
        \$count++;
    } catch(\\Exception \$e) {
        echo \"Error for {\$name}: \" . \$e->getMessage() . \"\\n\";
        \$skipped++;
    }
}

echo \"\\n\\nImported: {\$count} contacts\\nSkipped: {\$skipped}\\n\";
"
