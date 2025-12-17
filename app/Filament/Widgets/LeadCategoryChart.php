<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LeadCategoryChart extends ChartWidget
{
    protected ?string $heading = 'Lead Categories';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        // Group by 'lead_category' or 'vk_status'. Assuming lead_category exists from previous plan?
        // Checking task 1464 summary: "lead_category" might not exist yet?
        // SMM Analysis output `vk_status` (Active/Dead). Let's use that + 'Source'.
        // Or simply "Creation Source" breakdown.

        $data = Company::select('creation_source', DB::raw('count(*) as count'))
            ->groupBy('creation_source')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Companies',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#FF6384', // Red
                        '#36A2EB', // Blue
                        '#FFCE56', // Yellow
                        '#4BC0C0', // Teal
                        '#9966FF', // Purple
                    ],
                ],
            ],
            'labels' => $data->pluck('creation_source')->map(fn($s) => $s ?? 'Unknown')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
