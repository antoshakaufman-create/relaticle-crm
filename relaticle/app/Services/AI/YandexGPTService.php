<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class YandexGPTService
{
    private ?string $apiKey;
    private ?string $folderId;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('ai.yandex.api_key');
        $this->folderId = config('ai.yandex.folder_id');
        $this->baseUrl = config('ai.yandex.base_url', 'https://llm.api.cloud.yandex.net/foundationModels/v1');
        $this->model = config('ai.yandex.model', 'yandexgpt/latest');
    }

    /**
     * Поиск информации через YandexGPT
     */
    public function search(string $query, ?string $systemPrompt = null): ?array
    {
        if (!$this->apiKey || !$this->folderId) {
            Log::warning('YandexGPT API key or folder ID not configured');

            return null;
        }

        try {
            $messages = [];

            // Add system prompt if provided
            if ($systemPrompt !== null) {
                $messages[] = [
                    'role' => 'system',
                    'text' => $systemPrompt,
                ];
            }

            // Add user query
            $messages[] = [
                'role' => 'user',
                'text' => $query,
            ];

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Api-Key ' . $this->apiKey,
                    'x-folder-id' => $this->folderId,
                ])
                ->post("{$this->baseUrl}/completion", [
                    'modelUri' => "gpt://{$this->folderId}/{$this->model}",
                    'completionOptions' => [
                        'stream' => false,
                        'temperature' => 0.3,
                        'maxTokens' => 2000,
                    ],
                    'messages' => $messages,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'content' => $data['result']['alternatives'][0]['message']['text'] ?? '',
                    'data' => $this->parseContent($data['result']['alternatives'][0]['message']['text'] ?? ''),
                ];
            }

            Log::warning('YandexGPT API error: ' . $response->body());

            return null;
        } catch (\Exception $e) {
            Log::error('YandexGPT API exception: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Анализ достоверности данных лида
     */
    public function analyzeCredibility(array $leadData, array $validationResults): ?AIAnalysis
    {
        $prompt = $this->buildAnalysisPrompt($leadData, $validationResults);

        $result = $this->search($prompt);

        if (!$result) {
            return null;
        }

        return $this->parseAnalysis($result['content']);
    }

    private function buildAnalysisPrompt(array $leadData, array $validationResults): string
    {
        $prompt = "Проанализируй достоверность контактных данных лида на российском рынке и определи, не являются ли они вымышленными (mock данными).\n\n";
        $prompt .= "Данные лида:\n";
        $prompt .= "- Имя: " . ($leadData['name'] ?? 'не указано') . "\n";
        $prompt .= "- Email: " . ($leadData['email'] ?? 'не указано') . "\n";
        $prompt .= "- Телефон: " . ($leadData['phone'] ?? 'не указано') . "\n";
        $prompt .= "- Компания: " . ($leadData['company_name'] ?? 'не указано') . "\n";
        $prompt .= "- Должность: " . ($leadData['position'] ?? 'не указано') . "\n\n";

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
        $prompt .= '  "score": 0-100,' . "\n";
        $prompt .= '  "riskFactors": ["список факторов риска"],' . "\n";
        $prompt .= '  "recommendations": ["рекомендации"],' . "\n";
        $prompt .= '  "confidence": 0-1' . "\n";
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
    /**
     * Поиск и извлечение компаний через Search + GPT
     * 
     * @return array<int, array{name: string, description: string, url: string}>
     */
    public function discoverCompanies(string $query): array
    {
        $searchService = app(YandexSearchService::class);
        $searchResults = $searchService->search($query);

        if (empty($searchResults)) {
            return [];
        }

        // Contextualize the prompt with real search data
        $context = "Результаты поиска по запросу '$query':\n\n";
        foreach ($searchResults as $index => $result) {
            $context .= ($index + 1) . ". " . $result['title'] . "\n";
            $context .= "   URL: " . $result['url'] . "\n";
            $context .= "   Описание: " . $result['description'] . "\n\n";
        }

        $systemPrompt = "Ты - бизнес-аналитик. Твоя задача - извлечь информацию о РЕАЛЬНЫХ компаниях из предоставленных результатов поиска. Игнорируй каталоги, агрегаторы и информационные статьи. Ищи только официальные сайты компаний или их прямые представительства.";

        $prompt = $context . "\n\n";
        $prompt .= "На основе этих данных, составь список найденных компаний в формате JSON.\n";
        $prompt .= "Формат:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"name\": \"Название компании\",\n";
        $prompt .= "    \"description\": \"Краткое описание деятельности (на русском)\",\n";
        $prompt .= "    \"url\": \"Официальный сайт\",\n";
        $prompt .= "    \"confidence\": 0-100 (насколько это похоже на реальный бизнес)\n";
        $prompt .= "  }\n";
        $prompt .= "]\n";
        $prompt .= "Верни ТОЛЬКО JSON массив.";

        $response = $this->search($prompt, $systemPrompt); // Reuse existing completion method

        if (!$response || empty($response['content'])) {
            return [];
        }

        $parsed = $this->parseContent($response['content']);

        // Handle if parseContent returned array wrapper or direct array
        if (isset($parsed['text'])) {
            // Fallback if regex failed in parseContent but it might be raw json
            $json = json_decode($response['content'], true);
            return is_array($json) ? $json : [];
        }

        // If parsed is associative array but we expect list, wrap it? 
        // parseContent usually returns the decoded JSON. 
        // If GPT adhered to prompt, it's a list.
        return is_array($parsed) && array_is_list($parsed) ? $parsed : [];
    }

}

