<?php

use App\Models\Company;

// Target Company ID on Production
$companyId = 407;

$company = Company::find($companyId);

if (!$company) {
    echo "ERROR: Company with ID $companyId not found." . PHP_EOL;
    exit(1);
}

echo "Found company: " . $company->name . " (ID: " . $company->id . ")" . PHP_EOL;

$links = [
    [
        'title' => 'Березовская ГРЭС',
        'url' => 'https://vk.com/bgresunipro',
        'status' => 'active',
        'verified_at' => now()->toIso8601String(),
    ],
    [
        'title' => 'Смоленская ГРЭС',
        'url' => 'https://vk.com/smgres',
        'status' => 'active',
        'verified_at' => now()->toIso8601String(),
    ],
    [
        'title' => 'Сургутская ГРЭС-2',
        'url' => 'https://vk.com/public193489057',
        'status' => 'active',
        'verified_at' => now()->toIso8601String(),
    ],
    [
        'title' => 'Яйвинская ГРЭС',
        'url' => 'https://vk.com/ygres',
        'status' => 'active',
        'verified_at' => now()->toIso8601String(),
    ],
    [
        'title' => 'Telegram',
        'url' => 'https://t.me/unipronrg',
        'status' => 'active',
        'verified_at' => now()->toIso8601String(),
    ],
    [
        'title' => 'Website',
        'url' => 'https://www.unipro.energy/',
        'status' => 'active',
        'verified_at' => now()->toIso8601String(),
    ],
];

$currentSmm = $company->smm_analysis ?? [];
$currentSmm['related_links'] = $links;

$company->smm_analysis = $currentSmm;
$company->save();

echo "Successfully updated 'related_links' for Company ID $companyId." . PHP_EOL;
