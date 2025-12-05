<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GigaChatService
{
    private ?string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('ai.gigachat.api_key');
        $this->baseUrl = config('ai.gigachat.base_url', 'https://gigachat.devices.sberbank.ru/api/v1');
        $this->model = config('ai.gigachat.model', 'GigaChat-Pro');
    }

    /**
     * Поиск информации через GigaChat с веб-поиском
     */
    public function search(string $query, bool $webSearch = true): ?array
    {
        if (!$this->apiKey) {
            Log::warning('GigaChat API key not configured');

            return null;
        }

        try {
            $payload = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $query,
                    ],
                ],
                'temperature' => 0.2,
            ];

            if ($webSearch) {
                $payload['web_search'] = true;
            }

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'content' => $data['choices'][0]['message']['content'] ?? '',
                    'data' => $this->parseContent($data['choices'][0]['message']['content'] ?? ''),
                ];
            }

            Log::warning('GigaChat API error: '.$response->body());

            return null;
        } catch (\Exception $e) {
            Log::error('GigaChat API exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Анализ достоверности данных лида
     */
    public function analyzeCredibility(array $leadData, array $validationResults): ?AIAnalysis
    {
        $prompt = $this->buildAnalysisPrompt($leadData, $validationResults);

        $result = $this->search($prompt, true);

        if (!$result) {
            return null;
        }

        return $this->parseAnalysis($result['content']);
    }

    private function buildAnalysisPrompt(array $leadData, array $validationResults): string
    {
        $prompt = "Проанализируй достоверность контактных данных лида на российском рынке и определи, не являются ли они вымышленными (mock данными).\n\n";
        $prompt .= "Данные лида:\n";
        $prompt .= "- Имя: ".($leadData['name'] ?? 'не указано')."\n";
        $prompt .= "- Email: ".($leadData['email'] ?? 'не указано')."\n";
        $prompt .= "- Телефон: ".($leadData['phone'] ?? 'не указано')."\n";
        $prompt .= "- Компания: ".($leadData['company_name'] ?? 'не указано')."\n";
        $prompt .= "- Должность: ".($leadData['position'] ?? 'не указано')."\n\n";

        $prompt .= "Результаты валидации:\n";
        foreach ($validationResults as $field => $result) {
            if ($result instanceof \App\Services\LeadValidation\ValidationResult) {
                $prompt .= "- {$field}: {$result->status} (score: {$result->score})\n";
            }
        }

        $prompt .= "\nПроверь с учетом российского рынка:\n";
        $prompt .= "1. Консистентность данных (соответствие имени, компании, должности в российском контексте)\n";
        $prompt .= "2. Паттерны, характерные для mock-данных в России\n";
        $prompt .= "3. Реалистичность комбинаций данных для российского бизнеса\n";
        $prompt .= "4. Соответствие форматам реальных российских контактов\n";
        $prompt .= "5. Проверь существование компании через российские источники\n\n";

        $prompt .= "Верни JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "score": 0-100,'."\n";
        $prompt .= '  "riskFactors": ["список факторов риска"],'."\n";
        $prompt .= '  "recommendations": ["рекомендации"],'."\n";
        $prompt .= '  "confidence": 0-1'."\n";
        $prompt .= "}\n";

        return $prompt;
    }

    private function parseContent(string $content): array
    {
        // Пытаемся извлечь JSON из ответа
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        // Если JSON не найден, возвращаем текст
        return ['text' => $content];
    }

    private function parseAnalysis(string $content): AIAnalysis
    {
        $data = $this->parseContent($content);

        return new AIAnalysis(
            credibilityScore: (int) ($data['score'] ?? 50),
            riskFactors: $data['riskFactors'] ?? [],
            recommendations: $data['recommendations'] ?? [],
            confidence: (float) ($data['confidence'] ?? 0.5)
        );
    }
}



