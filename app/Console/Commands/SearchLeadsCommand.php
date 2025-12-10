<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeadGeneration\HybridLeadService;
use Illuminate\Console\Command;

final class SearchLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:search
                            {query : Поисковый запрос для поиска лидов}
                            {--city= : Город для фильтрации}
                            {--industry= : Отрасль для фильтрации}
                            {--company-size= : Размер компании}
                            {--provider=hybrid : Провайдер поиска (gigachat, yandexgpt, hybrid)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Поиск лидов через AI (GigaChat/YandexGPT)';

    /**
     * Execute the console command.
     */
    public function handle(HybridLeadService $leadService): int
    {
        $query = $this->argument('query');
        $filters = array_filter([
            'city' => $this->option('city'),
            'industry' => $this->option('industry'),
            'company_size' => $this->option('company-size'),
        ]);

        $this->info("Поиск лидов по запросу: {$query}");

        if (!empty($filters)) {
            $this->line('Фильтры: '.json_encode($filters, JSON_UNESCAPED_UNICODE));
        }

        $this->newLine();

        $bar = $this->output->createProgressBar();
        $bar->start();

        try {
            $leads = $leadService->searchLeads($query, $filters);

            $bar->finish();
            $this->newLine(2);

            $count = $leads->count();

            if ($count > 0) {
                $this->info("Найдено лидов: {$count}");

                $this->table(
                    ['Имя', 'Email', 'Телефон', 'Компания', 'Статус', 'Score'],
                    $leads->map(fn ($lead) => [
                        $lead->name ?? '-',
                        $lead->email ?? '-',
                        $lead->phone ?? '-',
                        $lead->company_name ?? '-',
                        $lead->validation_status->getLabel(),
                        $lead->validation_score ?? 0,
                    ])->toArray()
                );
            } else {
                $this->warn('Лиды не найдены');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error('Ошибка при поиске лидов: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
