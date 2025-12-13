<?php
/**
 * Import companies and contacts from JSON file
 * Run: php8.5 /tmp/import_full.php
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
echo "Contacts to import: " . count($contactsData) . "\n\n";

// Delete existing data
echo "=== Clearing existing data ===\n";
People::query()->forceDelete();
Company::query()->forceDelete();
echo "Done\n\n";

// Create companies
echo "=== Importing companies ===\n";
$companyMap = []; // import_id => db_id

foreach ($companiesData as $comp) {
    $name = $comp['name'] ?? '';
    if (empty($name) || strlen($name) < 2)
        continue;

    $website = $comp['website'] ?? '';
    if (!empty($website) && strpos($website, 'http') !== 0) {
        $website = 'https://' . $website;
    }

    $notes = '';
    if (!empty($comp['services'])) {
        $notes .= "Услуги: " . $comp['services'] . "\n";
    }
    if (!empty($comp['comment'])) {
        $notes .= "Комментарий: " . $comp['comment'];
    }

    try {
        $company = Company::create([
            'team_id' => 1,
            'creator_id' => 1,
            'name' => $name,
            'industry' => $comp['industry'] ?? null,
            'website' => $website ?: null,
            'notes' => trim($notes) ?: null,
            'creation_source' => 'import',
        ]);

        $companyMap[$comp['import_id']] = $company->id;
        echo ".";
    } catch (Exception $e) {
        echo "E";
    }
}

echo "\n\nCompanies created: " . count($companyMap) . "\n\n";

// Create contacts
echo "=== Importing contacts ===\n";
$contactCount = 0;

foreach ($contactsData as $contact) {
    $name = $contact['full_name'] ?? '';
    if (empty($name) || $name === 'Не указано' || strlen($name) < 2)
        continue;

    // Clean email
    $email = $contact['email'] ?? '';
    $email = str_replace("'", '', $email);
    $email = preg_replace('/;.*/', '', $email);
    $email = trim($email);

    // Clean phone
    $phone = $contact['phone'] ?? '';
    $phone = trim($phone);

    // Get company ID
    $companyId = null;
    $companyImportId = $contact['company_import_id'] ?? '';
    if (!empty($companyImportId) && isset($companyMap[$companyImportId])) {
        $companyId = $companyMap[$companyImportId];
    }

    // Build notes
    $notes = '';
    if (!empty($contact['comment'])) {
        $notes = "Комментарий: " . $contact['comment'];
    }

    try {
        People::create([
            'team_id' => 1,
            'creator_id' => 1,
            'company_id' => $companyId,
            'name' => $name,
            'position' => $contact['job_title'] !== 'Не указано' ? $contact['job_title'] : null,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'notes' => trim($notes) ?: null,
            'source' => 'DOCX Import',
            'creation_source' => 'import',
        ]);
        $contactCount++;
        echo ".";
    } catch (Exception $e) {
        echo "E";
    }
}

echo "\n\nContacts created: $contactCount\n";

echo "\n=== Final Stats ===\n";
echo "Companies: " . Company::count() . "\n";
echo "Contacts: " . People::count() . "\n";
