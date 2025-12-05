<?php

declare(strict_types=1);

namespace App\Services\LeadValidation;

use App\Enums\LeadValidationStatus;
use App\Services\AI\AICredibilityAnalyzer;

final class LeadValidationService
{
    public function __construct(
        private EmailValidationService $emailValidator,
        private PhoneValidationService $phoneValidator,
        private CompanyValidationService $companyValidator,
        private SocialMediaValidationService $socialValidator,
    ) {}

    public function validateLead(array $leadData): LeadValidationResult
    {
        $results = [];
        $scores = [];

        // 1. Email валидация
        if (!empty($leadData['email'])) {
            $emailResult = $this->emailValidator->validate($leadData['email']);
            $results['email'] = $emailResult;
            $scores['email'] = $emailResult->getScore();
        }

        // 2. Phone валидация
        if (!empty($leadData['phone'])) {
            $phoneResult = $this->phoneValidator->validate($leadData['phone']);
            $results['phone'] = $phoneResult;
            $scores['phone'] = $phoneResult->getScore();
        }

        // 3. Company валидация
        if (!empty($leadData['company_name'])) {
            $companyResult = $this->companyValidator->validate(
                $leadData['company_name'],
                $leadData['inn'] ?? null
            );
            $results['company'] = $companyResult;
            $scores['company'] = $companyResult->getScore();
        }

        // 4. ВКонтакте валидация
        if (!empty($leadData['vk_url'])) {
            $vkResult = $this->socialValidator->validateVK(
                $leadData['vk_url'],
                $leadData['name'] ?? ''
            );
            $results['vk'] = $vkResult;
            $scores['vk'] = $vkResult->getScore();
        }

        // 5. Telegram валидация
        if (!empty($leadData['telegram_username'])) {
            $tgResult = $this->socialValidator->validateTelegram(
                $leadData['telegram_username'],
                $leadData['name'] ?? ''
            );
            $results['telegram'] = $tgResult;
            $scores['telegram'] = $tgResult->getScore();
        }

        // 6. AI-анализ достоверности (если доступен)
        $aiAnalysis = null;
        try {
            $aiAnalyzer = app(AICredibilityAnalyzer::class);
            if ($aiAnalyzer) {
                $aiAnalysis = $aiAnalyzer->analyze($leadData, $results);
                if ($aiAnalysis) {
                    $scores['ai'] = $aiAnalysis->credibilityScore;
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки AI (если сервис не настроен)
        }

        // 7. Итоговый расчет
        $finalScore = $this->calculateFinalScore($scores, $aiAnalysis);
        $status = $this->determineStatus($finalScore, $results);

        return new LeadValidationResult(
            status: $status,
            score: $finalScore,
            details: $results,
            aiAnalysis: $aiAnalysis ? $aiAnalysis->toArray() : null,
            recommendations: $this->generateRecommendations($results)
        );
    }

    private function calculateFinalScore(array $scores, ?object $aiAnalysis): int
    {
        // Взвешенная оценка
        $weights = [
            'email' => 0.3,
            'phone' => 0.2,
            'company' => 0.25,
            'vk' => 0.1,
            'telegram' => 0.05,
            'ai' => 0.1,
        ];

        $total = 0;
        foreach ($scores as $field => $score) {
            $total += $score * ($weights[$field] ?? 0);
        }

        if ($aiAnalysis && isset($scores['ai'])) {
            $total += $scores['ai'] * $weights['ai'];
        }

        return (int) round($total);
    }

    private function determineStatus(int $score, array $results): LeadValidationStatus
    {
        // Если есть явные mock-данные
        foreach ($results as $result) {
            if ($result instanceof ValidationResult && $result->isMock) {
                return LeadValidationStatus::MOCK;
            }
        }

        // Определение по score
        if ($score >= 80) {
            return LeadValidationStatus::VERIFIED;
        }

        if ($score >= 50) {
            return LeadValidationStatus::SUSPICIOUS;
        }

        return LeadValidationStatus::INVALID;
    }

    private function generateRecommendations(array $results): array
    {
        $recommendations = [];

        foreach ($results as $field => $result) {
            if ($result instanceof ValidationResult) {
                if ($result->status === 'invalid' && $result->error) {
                    $recommendations[] = "{$field}: {$result->error}";
                } elseif ($result->status === 'suspicious') {
                    $recommendations[] = "{$field}: требует дополнительной проверки";
                }
            }
        }

        return $recommendations;
    }
}

