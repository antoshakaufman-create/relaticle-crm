#!/bin/bash

# Import clients from CSV to CRM database
cd /var/www/relaticle

php8.5 artisan tinker --execute="
\$csv = file_get_contents('/var/www/relaticle/Клиенты-2.csv');
\$lines = explode(\"\n\", \$csv);
\$headers = str_getcsv(array_shift(\$lines), ',', '\"', '');

\$count = 0;
\$skipped = 0;

// Get current team
\$team = \\App\\Models\\Team::first();
if(!\$team) {
    echo 'No team found!';
    exit;
}

foreach(\$lines as \$line) {
    if(empty(trim(\$line))) continue;
    
    \$data = str_getcsv(\$line, ',', '\"', '');
    if(count(\$data) < 2) continue;
    
    \$company = trim(\$data[0] ?? '');
    if(empty(\$company) || strlen(\$company) < 2) {
        \$skipped++;
        continue;
    }
    
    \$industry = trim(\$data[1] ?? '');
    \$contact = trim(\$data[2] ?? '');
    \$phone = trim(\$data[3] ?? '');
    \$email = trim(\$data[4] ?? '');
    \$website = trim(\$data[5] ?? '');
    \$services = trim(\$data[7] ?? '');
    \$comments = trim(\$data[8] ?? '');
    
    // Clean up email - take first valid one
    \$email = str_replace([\"'\", ' '], '', \$email);
    \$emails = preg_split('/[;,]/', \$email);
    \$email = trim(\$emails[0] ?? '');
    if(!filter_var(\$email, FILTER_VALIDATE_EMAIL)) {
        \$email = null;
    }
    
    // Clean phone
    \$phone = preg_replace('/[^0-9+]/', '', \$phone);
    if(strlen(\$phone) < 7) \$phone = null;
    
    // Parse contact name and position
    \$contactName = \$contact;
    \$position = '';
    if(preg_match('/^([^(]+)\\(([^)]+)\\)/', \$contact, \$m)) {
        \$contactName = trim(\$m[1]);
        \$position = trim(\$m[2]);
    }
    
    // Build source details with extra info
    \$sourceDetails = implode(\"\\n\", array_filter([
        \"Отрасль: {\$industry}\",
        \"Контакт: {\$contact}\",
        \"Сайт: {\$website}\",
        \"Услуги: {\$services}\",
        \"Комментарии: {\$comments}\"
    ]));
    
    try {
        \$lead = \\App\\Models\\Lead::create([
            'name' => \$contactName ?: \$company,
            'company_name' => \$company,
            'position' => \$position,
            'phone' => \$phone,
            'email' => \$email,
            'team_id' => \$team->id,
            'source' => 'CSV Import',
            'source_details' => \$sourceDetails,
            'validation_status' => 'pending',
        ]);
        \$count++;
    } catch(\\Exception \$e) {
        echo \"Error: \" . \$e->getMessage() . \"\\n\";
        \$skipped++;
    }
}

echo \"\\n\\nImported: {\$count} leads\\nSkipped: {\$skipped}\\n\";
"
