<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class DashboardStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Revenue', Number::currency(Opportunity::sum('amount') ?? 0, 'USD'))
                ->description('Total opportunity value')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),

            Stat::make('New Leads', Lead::where('created_at', '>=', now()->subDays(30))->count())
                ->description('Last 30 days')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart([1, 10, 3, 12, 1, 14, 10, 1, 2, 10])
                ->color('info'),

            Stat::make('Open Tasks', Task::where('status', '!=', 'completed')->count())
                ->description('Pending actions')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('warning'),
        ];
    }
}
