<?php

namespace App\Filament\Widgets;

use App\Models\People;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DashboardStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('New People', People::where('created_at', '>=', now()->subDays(30))->count())
                ->description('30 days increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Companies', DB::table('companies')->count())
                ->color('primary'),
            Stat::make('Average Lead Score', number_format(DB::table('companies')->avg('lead_score') ?? 0, 1))
                ->color('warning'),
        ];
    }
}
