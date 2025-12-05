<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\LeadValidation\ValidationResult;

final class AICredibilityAnalyzer
{
    public function __construct(
        private HybridAIService $hybridAI,
    ) {}

    public function analyze(array $leadData, array $validationResults): AIAnalysis
    {
        $analysis = $this->hybridAI->analyzeLead($leadData, $validationResults);

        if (!$analysis) {
            // Возвращаем нейтральный анализ если AI недоступен
            return new AIAnalysis(
                credibilityScore: 50,
                riskFactors: ['AI анализ недоступен'],
                recommendations: ['Проверьте настройки AI сервисов'],
                confidence: 0.0
            );
        }

        return $analysis;
    }
}



