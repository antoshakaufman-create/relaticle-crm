<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use Filament\Widgets\ChartWidget;

class SmmPerformanceChart extends ChartWidget
{
    protected ?string $heading = 'Top SMM Performers (ER %)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $companies = Company::whereNotNull('er_score')
            ->orderByDesc('er_score')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Engagement Rate (ER)',
                    'data' => $companies->pluck('er_score')->toArray(),
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#9BD0F5',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $companies->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
