#!/bin/bash

# Enrich leads using YandexGPT API
cd /var/www/relaticle

php8.5 artisan tinker --execute="
use App\Services\AI\YandexGPTService;
use App\Models\Lead;

\$yandex = app(YandexGPTService::class);

// Get first 10 leads to enrich
\$leads = Lead::whereNull('enrichment_data')
    ->where('company_name', '!=', '')
    ->limit(10)
    ->get();

echo \"Found \" . \$leads->count() . \" leads to enrich\\n\\n\";

foreach(\$leads as \$lead) {
    echo \"Processing: {\$lead->company_name}\\n\";
    
    \$prompt = \"Найди информацию о российской компании '{\$lead->company_name}'. 
    Отрасль: {\$lead->source_details}
    
    Верни JSON с полями:
    - website: официальный сайт
    - vk_url: страница ВКонтакте
    - telegram: телеграм канал
    - linkedin: linkedin страница
    - description: краткое описание компании (1-2 предложения)
    - employees: примерное число сотрудников
    - revenue: примерный оборот если известен
    - key_contacts: список ключевых контактов если известны
    
    Отвечай ТОЛЬКО JSON без пояснений.\";
    
    try {
        \$result = \$yandex->search(\$prompt);
        
        if(\$result && isset(\$result['data'])) {
            \$lead->update([
                'enrichment_data' => \$result['data'],
                'vk_url' => \$result['data']['vk_url'] ?? null,
                'telegram_username' => \$result['data']['telegram'] ?? null,
                'linkedin_url' => \$result['data']['linkedin'] ?? null,
            ]);
            echo \"  ✓ Enriched with: \" . json_encode(\$result['data'], JSON_UNESCAPED_UNICODE) . \"\\n\";
        } else {
            echo \"  ✗ No data returned\\n\";
        }
    } catch(\\Exception \$e) {
        echo \"  ✗ Error: \" . \$e->getMessage() . \"\\n\";
    }
    
    // Small delay to avoid rate limiting
    usleep(500000);
}

echo \"\\n=== Done ===\";
"
