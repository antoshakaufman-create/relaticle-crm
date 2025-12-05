<?php

declare(strict_types=1);

namespace App\Services\LeadValidation;

use App\Enums\LeadValidationStatus;

final class LeadValidationResult
{
    public function __construct(
        public readonly LeadValidationStatus $status,
        public readonly int $score,
        public readonly array $details = [],
        public readonly ?array $aiAnalysis = null,
        public readonly array $recommendations = [],
    ) {}

    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->details as $field => $result) {
            if ($result instanceof ValidationResult && $result->error) {
                $errors[$field] = $result->error;
            }
        }

        return $errors;
    }

    public function getEnrichmentData(): array
    {
        $enrichment = [];
        foreach ($this->details as $field => $result) {
            if ($result instanceof ValidationResult && !empty($result->details)) {
                $enrichment[$field] = $result->details;
            }
        }

        return $enrichment;
    }
}



