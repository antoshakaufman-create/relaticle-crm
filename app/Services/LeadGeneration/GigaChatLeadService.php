<?php

declare(strict_types=1);

namespace App\Services\LeadGeneration;

use App\Models\Lead;
use App\Services\AI\GigaChatService;
use App\Services\LeadValidation\LeadValidationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class GigaChatLeadService
{
    public function __construct(
        private GigaChatService $gigaChat,
        private LeadValidationService $validationService,
    ) {}

    /**
     * Поиск лидов через GigaChat
     */
    public function searchLeads(string $query, array $filters = []): Collection
    {
        $prompt = $this->buildLeadSearchPrompt($query, $filters);

        $result = $this->gigaChat->search($prompt, true);

        if (!$result) {
            Log::warning('GigaChat search failed');

            return collect([]);
        }

        $leadsData = $this->parseLeadsFromResponse($result['content'] ?? '');

        // Валидируем каждый лид
        return $this->validateAndCreateLeads($leadsData);
    }

    private function buildLeadSearchPrompt(string $query, array $filters): string
    {
        $prompt = "Найди потенциальных клиентов (лидов) для SMM-агентства на российском рынке.\n\n";
        $prompt .= "Запрос: {$query}\n\n";

        if (!empty($filters['city'])) {
            $prompt .= "Город: {$filters['city']}\n";
        }

        if (!empty($filters['industry'])) {
            $prompt .= "Отрасль: {$filters['industry']}\n";
        }

        if (!empty($filters['company_size'])) {
            $prompt .= "Размер компании: {$filters['company_size']}\n";
        }

        $prompt .= "\nДля каждого лида найди:\n";
        $prompt .= "1. Название компании (полное юридическое название)\n";
        $prompt .= "2. ИНН компании (если возможно найти)\n";
        $prompt .= "3. Имя и должность ЛПР (лица, принимающего решения)\n";
        $prompt .= "4. Email контактного лица (если доступен)\n";
        $prompt .= "5. Телефон компании или контактного лица\n";
        $prompt .= "6. Сайт компании\n";
        $prompt .= "7. Адрес компании (город, адрес)\n";
        $prompt .= "8. Профиль ВКонтакте или Telegram (если есть)\n";
        $prompt .= "9. LinkedIn профиль (если есть)\n\n";

        $prompt .= "ВАЖНО:\n";
        $prompt .= "- Ищи информацию в российских источниках: Контур.Компас, Rusprofile, 2GIS, Яндекс.Справочник\n";
        $prompt .= "- Возвращай только реальные, проверяемые данные\n";
        $prompt .= "- Избегай тестовых или вымышленных данных\n";
        $prompt .= "- Если не можешь найти достоверную информацию - не выдумывай\n";
        $prompt .= "- Формат ответа: JSON массив объектов\n\n";

        $prompt .= "Верни результат в формате JSON:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= '    "company_name": "полное название",'."\n";
        $prompt .= '    "inn": "ИНН",'."\n";
        $prompt .= '    "contact_name": "Имя Фамилия",'."\n";
        $prompt .= '    "position": "Должность",'."\n";
        $prompt .= '    "email": "email@domain.ru",'."\n";
        $prompt .= '    "phone": "+7XXXXXXXXXX",'."\n";
        $prompt .= '    "website": "https://...",'."\n";
        $prompt .= '    "address": "город, адрес",'."\n";
        $prompt .= '    "vk_url": "https://vk.com/...",'."\n";
        $prompt .= '    "telegram": "@username",'."\n";
        $prompt .= '    "linkedin_url": "https://linkedin.com/...",'."\n";
        $prompt .= '    "source": "источник информации"'."\n";
        $prompt .= "  }\n";
        $prompt .= "]\n";

        return $prompt;
    }

    private function parseLeadsFromResponse(string $content): array
    {
        // Извлекаем JSON из ответа (может быть обернут в текст)
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $json = $matches[0];
            $leads = json_decode($json, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($leads)) {
                return $leads;
            }
        }

        // Если не удалось распарсить JSON, возвращаем пустой массив
        Log::warning('Failed to parse leads from GigaChat response');

        return [];
    }

    private function validateAndCreateLeads(array $leadsData): Collection
    {
        return collect($leadsData)->map(function ($leadData) {
            try {
                // Валидируем лид
                $validationResult = $this->validationService->validateLead($leadData);

                // Создаем только если прошел валидацию
                if ($validationResult->status->value !== 'invalid' &&
                    $validationResult->status->value !== 'mock') {

                    // Получаем текущую команду из контекста или используем первую доступную
                    $teamId = $this->getTeamId();

                    return Lead::create([
                        'team_id' => $teamId,
                        'creator_id' => auth()->id(),
                        'name' => $leadData['contact_name'] ?? null,
                        'email' => $leadData['email'] ?? null,
                        'phone' => $leadData['phone'] ?? null,
                        'company_name' => $leadData['company_name'] ?? null,
                        'position' => $leadData['position'] ?? null,
                        'linkedin_url' => $leadData['linkedin_url'] ?? null,
                        'vk_url' => $leadData['vk_url'] ?? null,
                        'telegram_username' => $leadData['telegram'] ?? null,
                        'source' => 'gigachat',
                        'source_details' => $leadData['source'] ?? null,
                        'validation_status' => $validationResult->status->value,
                        'validation_score' => $validationResult->score,
                        'validation_errors' => $validationResult->getErrors(),
                        'enrichment_data' => array_merge(
                            $validationResult->getEnrichmentData(),
                            [
                                'inn' => $leadData['inn'] ?? null,
                                'website' => $leadData['website'] ?? null,
                                'address' => $leadData['address'] ?? null,
                            ]
                        ),
                    ]);
                }

                return null;
            } catch (\Exception $e) {
                Log::error('Error creating lead: '.$e->getMessage());

                return null;
            }
        })->filter();
    }

    private function getTeamId(): int
    {
        // Пытаемся получить team_id из контекста
        if (auth()->check() && auth()->user()->currentTeam) {
            return auth()->user()->currentTeam->id;
        }

        // Или берем первую команду пользователя
        if (auth()->check()) {
            $team = auth()->user()->teams()->first();
            if ($team) {
                return $team->id;
            }
        }

        // Fallback: первая команда в системе (для CLI команд)
        $team = \App\Models\Team::first();
        if ($team) {
            return $team->id;
        }

        throw new \RuntimeException('No team available for lead creation');
    }
}

