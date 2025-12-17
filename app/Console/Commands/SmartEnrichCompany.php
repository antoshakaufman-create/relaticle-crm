<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmartEnrichCompany extends Command
{
    protected $signature = 'app:smart-enrich';
    protected $description = 'Enrich companies using YandexGPT to find legal names first';

    private $dadataKey = 'd727a93a800dd5572305eb876d66c44c3099813a';

    public function handle()
    {
        $this->info("Starting Smart Enrichment...");

        // Get config
        $aiKey = config('ai.yandex.api_key');
        $folderId = config('ai.yandex.folder_id');
        $baseUrl = config('ai.yandex.base_url', 'https://llm.api.cloud.yandex.net/foundationModels/v1');

        if (!$aiKey || !$folderId) {
            $this->error("YandexGPT not configured.");
            return;
        }

        $companies = Company::whereNull('inn')->orWhere('inn', '')->get();
        $total = $companies->count();
        $this->info("Found {$total} companies to smart-enrich.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($companies as $company) {
            // 1. Ask YandexGPT for Legal Name
            $legalName = $this->askYandex($company, $aiKey, $folderId, $baseUrl);

            if ($legalName) {
                // 2. Ask DaData with suggested name
                $enrichedData = $this->enrichWithDaData($company, $legalName);

                // 3. If enriched, try to find website using the OFFICIAL legal name
                if ($enrichedData) {
                    if (empty($company->website)) {
                        $website = $this->askYandexForWebsite($company, $aiKey, $folderId, $baseUrl);
                        if ($website) {
                            $company->website = $website;
                            $company->save();
                        }
                    }
                }
            }

            $bar->advance();
            usleep(500000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done.");
    }

    private function askYandex($company, $key, $folder, $url)
    {
        // ... (Existing implementation of askYandex for legal name)
        $prompt = "Напиши только точное название юридического лица (например ООО \"Ромашка\") для компании \"{$company->name}\".";
        if ($company->website) {
            $prompt .= " Сайт: {$company->website}.";
        }
        $prompt .= " Если не знаешь, напиши SKIP.";
        return $this->callGpt($prompt, $key, $folder, $url);
    }

    private function askYandexForWebsite($company, $key, $folder, $url)
    {
        $prompt = "Напиши только домен официального сайта для юридического лица \"{$company->legal_name}\" (ИНН: {$company->inn}, Адрес: {$company->address_line_1}). " .
            "Если не знаешь - верни null. Пример: google.com. БЕЗ ЛИШНЕГО ТЕКСТА.";

        $text = $this->callGpt($prompt, $key, $folder, $url);

        // Cleanup
        if (!$text)
            return null;
        $text = str_replace(['https://', 'http://', '/'], '', $text);
        $text = strtolower($text);

        if (str_contains($text, '.') && !str_contains($text, 'null') && !str_contains($text, ' ') && strlen($text) < 50) {
            return $text;
        }
        return null;
    }

    private function callGpt($prompt, $key, $folder, $url)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Api-Key ' . $key,
                'x-folder-id' => $folder,
            ])->post($url . '/completion', [
                        'modelUri' => "gpt://{$folder}/yandexgpt/latest",
                        'completionOptions' => ['stream' => false, 'temperature' => 0.1, 'maxTokens' => 100],
                        'messages' => [['role' => 'user', 'text' => $prompt]],
                    ]);

            if ($response->successful()) {
                $text = $response->json()['result']['alternatives'][0]['message']['text'] ?? '';
                return trim($text);
            }
        } catch (\Exception $e) {
            // Log::error($e->getMessage());
        }
        return null;
    }

    private function enrichWithDaData(Company $company, $queryName)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->dadataKey,
                'Accept' => 'application/json',
            ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party', [
                        'query' => $queryName,
                        'count' => 1
                    ]);

            if ($response->successful()) {
                $json = $response->json();
                if (!empty($json['suggestions'])) {
                    $data = $json['suggestions'][0]['data'];
                    $value = $json['suggestions'][0]['value'];

                    $company->legal_name = $value;
                    $company->inn = $data['inn'] ?? null;
                    $company->ogrn = $data['ogrn'] ?? null;
                    $company->kpp = $data['kpp'] ?? null;
                    if (isset($data['management'])) {
                        $company->management_name = $data['management']['name'] ?? null;
                        $company->management_post = $data['management']['post'] ?? null;
                    }
                    $company->okved = $data['okved'] ?? null;
                    $company->status = $data['state']['status'] ?? null;

                    if (empty($company->address_line_1) && isset($data['address']['value'])) {
                        $company->address_line_1 = $data['address']['value'];
                    }

                    // Capture Email if available (rare but possible)
                    if (!empty($data['emails'])) {
                        // $data['emails'] is array of objects or strings? Checking docs... usually objects {value: "..."}
                        // Actually suggest/party structure for emails: array of {value, source, ...}
                        $email = $data['emails'][0]['value'] ?? null;
                        if ($email && empty($company->email_contact)) {
                            // Assuming we want to create a contact or save to company?
                            // Let's create a Generic Contact "Office"
                            \App\Models\People::create([
                                'company_id' => $company->id,
                                'team_id' => $company->team_id ?? 1,
                                'name' => 'DaData Contact',
                                'position' => 'Office',
                                'email' => $email,
                                'creation_source' => \App\Enums\CreationSource::AI_GENERATED
                            ]);
                        }
                    }

                    $company->save();
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }
}
