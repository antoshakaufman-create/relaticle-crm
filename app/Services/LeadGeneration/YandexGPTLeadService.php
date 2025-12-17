<?php

declare(strict_types=1);

namespace App\Services\LeadGeneration;

use App\Models\Lead;
use App\Services\AI\YandexGPTService;
use App\Services\LeadValidation\LeadValidationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class YandexGPTLeadService
{
    public function __construct(
        private YandexGPTService $yandexGPT,
        private LeadValidationService $validationService,
    ) {
    }

    /**
     * Поиск лидов через YandexGPT
     */
    public function searchLeads(string $query, array $filters = []): Collection
    {
        $prompt = $this->buildLeadSearchPrompt($query, $filters);

        $result = $this->yandexGPT->search($prompt);

        if (!$result) {
            Log::warning('YandexGPT search failed');

            return collect([]);
        }

        $leadsData = $this->parseLeadsFromResponse($result['content'] ?? '');

        // Валидируем каждый лид
        return $this->validateAndCreateLeads($leadsData);
    }

    /**
     * Поиск сотрудников для конкретной компании
     */
    public function findEmployeesForCompany(\App\Models\Company $company): array
    {
        $websiteInfo = $company->vk_url ? "VK: {$company->vk_url}" : "";
        $prompt = "Ты HR-ресчер. Твоя задача: Найти ТОП-менеджмент (Гендир, Основатель, Маркетинг-директор, Коммерческий директор) для компании:\n" .
            "Название: \"{$company->name}\" (Юр. имя: {$company->legal_name})\n" .
            "Адрес: {$company->address_line_1}\n" .
            "{$websiteInfo}\n\n" .
            "ВАЖНО:\n" .
            "1. Назови РЕАЛЬНЫХ людей, известных в публичных источниках (СМИ, LinkedIn, Rusprofile).\n" .
            "2. Если точных данных о 'Current' нет, укажи наиболее вероятно последних известных менеджеров.\n" .
            "3. Если есть LinkedIn профиль - укажи ссылку (linkedin.com/in/...).\n" .
            "4. Верни только JSON массив: [{ \"name\": \"Имя Фамилия\", \"position\": \"Должность\", \"linkedin_url\": \"ссылка или null\" }].\n" .
            "5. Если уверенных данных нет - верни пустой массив []. Не выдумывай имена.";

        $result = $this->yandexGPT->search($prompt);
        $content = $result['content'] ?? '';

        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $data = json_decode($matches[0], true);
            return is_array($data) ? $data : [];
        }

        return [];
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
        $prompt .= '    "company_name": "полное название",' . "\n";
        $prompt .= '    "inn": "ИНН",' . "\n";
        $prompt .= '    "contact_name": "Имя Фамилия",' . "\n";
        $prompt .= '    "position": "Должность",' . "\n";
        $prompt .= '    "email": "email@domain.ru",' . "\n";
        $prompt .= '    "phone": "+7XXXXXXXXXX",' . "\n";
        $prompt .= '    "website": "https://...",' . "\n";
        $prompt .= '    "address": "город, адрес",' . "\n";
        $prompt .= '    "vk_url": "https://vk.com/...",' . "\n";
        $prompt .= '    "telegram": "@username",' . "\n";
        $prompt .= '    "linkedin_url": "https://linkedin.com/...",' . "\n";
        $prompt .= '    "source": "источник информации"' . "\n";
        $prompt .= "  }\n";
        $prompt .= "]\n";

        return $prompt;
    }

    private function parseLeadsFromResponse(string $content): array
    {
        // Извлекаем JSON из ответа
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $json = $matches[0];
            $leads = json_decode($json, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($leads)) {
                return $leads;
            }
        }

        Log::warning('Failed to parse leads from YandexGPT response');

        return [];
    }

    private function validateAndCreateLeads(array $leadsData): Collection
    {
        return collect($leadsData)->map(function ($leadData) {
            try {
                $validationResult = $this->validationService->validateLead($leadData);

                if (
                    $validationResult->status->value !== 'invalid' &&
                    $validationResult->status->value !== 'mock'
                ) {

                    $teamId = $this->getTeamId();

                    return Lead::create([
                        'team_id' => $teamId,
                        'creator_id' => auth()->id(),
                        'name' => $leadData['contact_name'] ?? $leadData['name'] ?? null,
                        'email' => $leadData['email'] ?? null,
                        'phone' => $leadData['phone'] ?? null,
                        'company_name' => $leadData['company_name'] ?? null,
                        'position' => $leadData['position'] ?? null,
                        'linkedin_url' => $leadData['linkedin_url'] ?? null,
                        'vk_url' => $leadData['vk_url'] ?? null,
                        'telegram_username' => $leadData['telegram'] ?? null,
                        'source' => 'yandexgpt',
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
                Log::error('Error creating lead: ' . $e->getMessage());

                return null;
            }
        })->filter();
    }

    private function getTeamId(): int
    {
        if (auth()->check() && auth()->user()->currentTeam) {
            return auth()->user()->currentTeam->id;
        }

        if (auth()->check()) {
            $team = auth()->user()->teams()->first();
            if ($team) {
                return $team->id;
            }
        }

        $team = \App\Models\Team::first();
        if ($team) {
            return $team->id;
        }

        throw new \RuntimeException('No team available for lead creation');
    }
}

