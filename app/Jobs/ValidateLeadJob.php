<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadValidation\LeadValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ValidateLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Lead $lead
    ) {
        $this->onQueue('validation');
    }

    /**
     * Execute the job.
     */
    public function handle(LeadValidationService $validationService): void
    {
        try {
            $leadData = [
                'name' => $this->lead->name,
                'email' => $this->lead->email,
                'phone' => $this->lead->phone,
                'company_name' => $this->lead->company_name,
                'position' => $this->lead->position,
                'linkedin_url' => $this->lead->linkedin_url,
                'vk_url' => $this->lead->vk_url,
                'telegram_username' => $this->lead->telegram_username,
            ];

            $result = $validationService->validateLead($leadData);

            $this->lead->update([
                'validation_status' => $result->status->value,
                'validation_score' => $result->score,
                'validation_errors' => $result->getErrors(),
                'email_verified' => isset($result->details['email']) && $result->details['email']->isValid(),
                'phone_verified' => isset($result->details['phone']) && $result->details['phone']->isValid(),
                'company_verified' => isset($result->details['company']) && $result->details['company']->isValid(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error validating lead in job: '.$e->getMessage(), [
                'lead_id' => $this->lead->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
