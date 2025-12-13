#!/bin/bash

cd /var/www/relaticle

echo "=== Import Companies from CSV ==="

# First, upload the CSV to server
echo "Uploading CSV..."

php8.5 artisan tinker --execute="
use App\Models\People;
use Illuminate\Support\Facades\DB;

// Delete all existing people first
echo 'Deleting existing contacts...' . \"\\n\";
People::query()->forceDelete();
echo 'Deleted.' . \"\\n\\n\";

// Read CSV data (passed via stdin)
\$csvData = file_get_contents('php://stdin');
\$lines = explode(\"\\n\", \$csvData);

// Skip header
array_shift(\$lines);

\$imported = 0;
\$skipped = 0;

foreach (\$lines as \$line) {
    \$line = trim(\$line);
    if (empty(\$line)) continue;
    
    // Parse CSV line (handle quoted fields)
    \$fields = str_getcsv(\$line);
    
    if (count(\$fields) < 2) continue;
    
    \$company = trim(\$fields[0] ?? '');
    \$industry = trim(\$fields[1] ?? '');
    \$position = trim(\$fields[2] ?? '');
    \$phone = trim(\$fields[3] ?? '');
    \$email = trim(\$fields[4] ?? '');
    \$website = trim(\$fields[5] ?? '');
    \$date = trim(\$fields[6] ?? '');
    \$services = trim(\$fields[7] ?? '');
    \$comments = trim(\$fields[8] ?? '');
    
    // Skip if company is empty
    if (empty(\$company) || strlen(\$company) < 2) {
        \$skipped++;
        continue;
    }
    
    // Extract contact name from position field (e.g., 'Самсонов Михаил(Медицинский Директор)')
    \$contactName = \$company;
    \$contactPosition = '';
    
    if (!empty(\$position)) {
        // Try to extract name from position
        if (preg_match('/^([^(]+)\\((.+)\\)/', \$position, \$matches)) {
            \$contactName = trim(\$matches[1]);
            \$contactPosition = trim(\$matches[2]);
        } else {
            \$contactName = \$position;
        }
    }
    
    // Build notes
    \$notes = 'Компания: ' . \$company;
    if (!empty(\$contactPosition)) {
        \$notes .= \"\\nДолжность: \" . \$contactPosition;
    }
    if (!empty(\$services)) {
        \$notes .= \"\\nУслуги: \" . \$services;
    }
    if (!empty(\$comments)) {
        \$notes .= \"\\nКомментарии: \" . \$comments;
    }
    
    // Clean email
    \$email = str_replace(\"'\", '', \$email);
    \$email = preg_replace('/;.*/', '', \$email);
    \$email = trim(\$email);
    
    // Clean website
    \$website = str_replace('\"', '', \$website);
    \$website = trim(\$website);
    if (!empty(\$website) && !str_starts_with(\$website, 'http')) {
        \$website = 'https://' . \$website;
    }
    
    // Clean phone
    \$phone = trim(\$phone);
    
    // Create contact
    try {
        People::create([
            'team_id' => 1,
            'creator_id' => 1,
            'name' => \$contactName,
            'email' => \$email ?: null,
            'phone' => \$phone ?: null,
            'position' => \$contactPosition ?: null,
            'industry' => \$industry ?: null,
            'website' => \$website ?: null,
            'notes' => \$notes,
            'source' => 'CSV Import',
            'creation_source' => 'imported',
        ]);
        \$imported++;
    } catch (\\Exception \$e) {
        echo 'Error: ' . \$e->getMessage() . \"\\n\";
        \$skipped++;
    }
}

echo \"\\n=== Result ===\";
echo \"\\nImported: {\$imported}\";
echo \"\\nSkipped: {\$skipped}\";
"
