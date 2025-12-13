<?php
/**
 * Import companies and link to existing contacts
 * Run: php8.5 /tmp/import_companies_only.php
 */

require '/var/www/relaticle/vendor/autoload.php';
$app = require_once '/var/www/relaticle/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\People;
use App\Models\Company;

echo "=== Reading import data ===\n";
$json = file_get_contents('/tmp/import_data.json');
$data = json_decode($json, true);

$companiesData = $data['companies'];
$contactsData = $data['contacts'];

echo "Companies to import: " . count($companiesData) . "\n";
echo "Contacts in data: " . count($contactsData) . "\n\n";

// Delete existing companies
echo "=== Clearing existing companies ===\n";
Company::query()->forceDelete();
echo "Done\n\n";

// Create companies (without website/industry - not in table)
echo "=== Importing companies ===\n";
$companyMap = []; // import_id => db_id
$created = 0;

foreach ($companiesData as $comp) {
    $name = $comp['name'] ?? '';
    if (empty($name) || strlen($name) < 2)
        continue;

    try {
        $company = Company::create([
            'team_id' => 1,
            'creator_id' => 1,
            'name' => $name,
            'creation_source' => 'import',
        ]);

        $companyMap[$comp['import_id']] = [
            'id' => $company->id,
            'industry' => $comp['industry'] ?? '',
            'website' => $comp['website'] ?? '',
            'services' => $comp['services'] ?? '',
            'comment' => $comp['comment'] ?? '',
        ];
        $created++;
        echo ".";
    } catch (Exception $e) {
        echo "E";
    }
}

echo "\n\nCompanies created: $created\n\n";

// Link contacts to companies and add company info to notes
echo "=== Linking contacts to companies ===\n";
$linked = 0;

foreach ($contactsData as $contact) {
    $companyImportId = $contact['company_import_id'] ?? '';
    if (empty($companyImportId) || !isset($companyMap[$companyImportId]))
        continue;

    $companyData = $companyMap[$companyImportId];
    $companyId = $companyData['id'];

    // Find contact by name and email
    $name = $contact['full_name'] ?? '';
    $email = $contact['email'] ?? '';
    $email = str_replace("'", '', $email);
    $email = preg_replace('/;.*/', '', $email);
    $email = trim($email);

    $person = null;
    if (!empty($email)) {
        $person = People::where('email', $email)->first();
    }
    if (!$person && !empty($name)) {
        $person = People::where('name', $name)->first();
    }

    if ($person) {
        // Build notes with company info
        $notes = $person->notes ?? '';

        if (!empty($companyData['industry'])) {
            $notes .= "\nОтрасль: " . $companyData['industry'];
            $person->industry = $companyData['industry'];
        }
        if (!empty($companyData['website'])) {
            $website = $companyData['website'];
            if (!str_starts_with($website, 'http')) {
                $website = 'https://' . $website;
            }
            $notes .= "\nСайт: " . $website;
            $person->website = $website;
        }
        if (!empty($companyData['services'])) {
            $notes .= "\nУслуги: " . $companyData['services'];
        }
        if (!empty($companyData['comment'])) {
            $notes .= "\nКомментарий: " . $companyData['comment'];
        }

        $person->company_id = $companyId;
        $person->notes = trim($notes);
        $person->save();
        $linked++;
        echo ".";
    }
}

echo "\n\nContacts linked: $linked\n";

echo "\n=== Final Stats ===\n";
echo "Companies: " . Company::count() . "\n";
echo "Contacts: " . People::count() . "\n";
echo "Contacts with company: " . People::whereNotNull('company_id')->count() . "\n";
