<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;

$recent = Company::where('smm_analysis_date', '>=', now()->subMinutes(10))->count();
$totalVk = Company::whereNotNull('vk_url')->where('vk_url', '!=', '')->count();

echo "Companies with recent analysis: $recent\n";
echo "Total Companies with VK: $totalVk\n";
