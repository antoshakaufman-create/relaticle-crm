<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "--- Testing DashboardStatsOverview ---\n";
    $w1 = new \App\Filament\Widgets\DashboardStatsOverview();
    // Reflection to test protected getStats
    $r1 = new ReflectionMethod($w1, 'getStats');
    $r1->setAccessible(true);
    $s1 = $r1->invoke($w1);
    echo "Stats Count: " . count($s1) . "\n";
    echo "Stat 1: " . $s1[0]->getLabel() . " = " . $s1[0]->getValue() . "\n";

    echo "\n--- Testing SmmPerformanceChart ---\n";
    $w2 = new \App\Filament\Widgets\SmmPerformanceChart();
    $r2 = new ReflectionMethod($w2, 'getData');
    $r2->setAccessible(true);
    $d2 = $r2->invoke($w2);
    echo "Datasets: " . count($d2['datasets']) . "\n";
    echo "Labels: " . implode(', ', array_slice($d2['labels'], 0, 3)) . "...\n";

    echo "\n--- Testing LeadCategoryChart ---\n";
    $w3 = new \App\Filament\Widgets\LeadCategoryChart();
    $r3 = new ReflectionMethod($w3, 'getData');
    $r3->setAccessible(true);
    $d3 = $r3->invoke($w3);
    echo "Segments: " . count($d3['labels']) . "\n";

    echo "\n✅ ALL WIDGETS OK\n";
} catch (\Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
