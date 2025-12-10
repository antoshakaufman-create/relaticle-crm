<?php

namespace App\Filament\Widgets;

use App\Models\Opportunity;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Revenue Growth';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Opportunity::selectRaw('strftime("%Y-%m", created_at) as date, sum(amount) as total')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Opportunity Value',
                    'data' => $data->pluck('total'),
                    'fill' => true,
                    'borderColor' => '#4ade80',
                    'backgroundColor' => 'rgba(74, 222, 128, 0.2)',
                ],
            ],
            'labels' => $data->pluck('date'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
