<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadValidation\LeadValidationService;
use Illuminate\Console\Command;

final class RevalidateLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:revalidate
                            {--status=suspicious : Статус лидов для повторной валидации}
                            {--limit=50 : Максимальное количество лидов}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Повторная валидация лидов с определенным статусом';

    /**
     * Execute the console command.
     */
    public function handle(LeadValidationService $validationService): int
    {
        $status = $this->option('status');
        $limit = (int) $this->option('limit');

        $leads = Lead::where('validation_status', $status)
            ->limit($limit)
            ->get();

        if ($leads->isEmpty()) {
            $this->warn("Лиды со статусом '{$status}' не найдены");

            return self::SUCCESS;
        }

        $this->info("Повторная валидация {$leads->count()} лидов со статусом '{$status}'...");
        $this->newLine();

        $bar = $this->output->createProgressBar($leads->count());
        $bar->start();

        $revalidated = 0;
        $errors = 0;

        foreach ($leads as $lead) {
            try {
                $leadData = [
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'company_name' => $lead->company_name,
                    'position' => $lead->position,
                    'linkedin_url' => $lead->linkedin_url,
                    'vk_url' => $lead->vk_url,
                    'telegram_username' => $lead->telegram_username,
                ];

                $result = $validationService->validateLead($leadData);

                $lead->update([
                    'validation_status' => $result->status->value,
                    'validation_score' => $result->score,
                    'validation_errors' => $result->getErrors(),
                    'email_verified' => isset($result->details['email']) && $result->details['email']->isValid(),
                    'phone_verified' => isset($result->details['phone']) && $result->details['phone']->isValid(),
                    'company_verified' => isset($result->details['company']) && $result->details['company']->isValid(),
                ]);

                $revalidated++;
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Ошибка при повторной валидации лида #{$lead->id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Повторно валидировано: {$revalidated}");
        if ($errors > 0) {
            $this->warn("Ошибок: {$errors}");
        }

        return self::SUCCESS;
    }
}
