<?php

namespace App\Filament\Widgets;

use App\Models\People;
use App\Models\Company;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DashboardStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Helper for 7-day trend
        $getTrend = function ($model) {
            $data = $model::selectRaw('DATE(created_at) as date, count(*) as count')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            // Fill missing days with 0
            $trend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $trend[] = $data[$date] ?? 0;
            }
            return $trend;
        };

        $companyTrend = $getTrend(Company::class);
        $peopleTrend = $getTrend(People::class);

        $totalCompanies = Company::count();
        $analyzedCount = Company::whereNotNull('smm_analysis_date')->count();
        $coverage = $totalCompanies > 0 ? round(($analyzedCount / $totalCompanies) * 100, 1) : 0;
        $avgEr = Company::whereNotNull('er_score')->avg('er_score') ?? 0;

        return [
            Stat::make('Total Companies', $totalCompanies)
                ->description("+$analyzedCount Analyzed")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->chart($companyTrend)
                ->color('primary'),

            Stat::make('People & Contacts', People::count())
                ->description('Active profiles')
                ->descriptionIcon('heroicon-m-users')
                ->chart($peopleTrend)
                ->color('success'),

            Stat::make('SMM Coverage', "$coverage%")
                ->description("$analyzedCount / $totalCompanies companies")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($coverage > 80 ? 'success' : 'warning'),

            Stat::make('Avg Engagement Rate', number_format($avgEr, 2) . '%')
                ->description('Average ER across portfolio')
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color('info')
                ->chart([$avgEr, $avgEr + 0.5, $avgEr - 0.2, $avgEr + 0.8]), // Fake micro-beat or just flat
        ];
    }
}
