<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Team;
use App\Services\AI\YandexGPTService;
use App\Services\VkActionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GenerateSmartLeads extends Command
{
    protected $signature = 'app:generate-smart-leads {industry=Digital}';
    protected $description = 'Generate high-quality leads using YandexGPT + DaData + VK Validation';

    private string $dadataKey = 'd727a93a800dd5572305eb876d66c44c3099813a';
    private string $dadataSecret = '9b0a6065099a43fb24e0d4ac98f95482107b2d29';

    public function handle(YandexGPTService $gpt, VkActionService $vk)
    {
        $industry = $this->argument('industry');
        $target = 20;
        $addedCount = 0;
        $processedNames = [];

        $this->info("=== Starting AI Lead Generation for Industry: $industry ===");
        $this->info("=== Target: $target valid leads ===");

        while ($addedCount < $target) {

            $needed = $target - $addedCount;
            $this->info("\n--- Loop Start (Added: $addedCount/$target) ---");
            $this->info("[Step 1] Asking YandexGPT for suggestions (Batch size: 50)...");

            $candidates = $this->askGpt($gpt, $industry, $processedNames);

            if (empty($candidates)) {
                $this->error("No more candidates returned from GPT. Stopping.");
                break;
            }

            $this->info("GPT returned " . count($candidates) . " unique candidates.");

            foreach ($candidates as $candidate) {
                if ($addedCount >= $target)
                    break;

                $candidateName = $candidate['name'];
                $candidateWebsite = $candidate['website'];

                // Track checked names to avoid infinite GPT loops
                if (in_array($candidateName, $processedNames))
                    continue;
                $processedNames[] = $candidateName;

                $this->info("\nProcessing: $candidateName (Site: $candidateWebsite)");

                // STEP 2: DaData Verification (Moscow + Active)
                $legalData = $this->checkDaData($candidateName);

                if (!$legalData) {
                    $this->warn("   -> [X] Step 2 Failed: Not found, inactive, or not in Moscow.");
                    continue;
                }

                $this->info("   -> [V] Step 2 Passed: Found '{$legalData['name']}' (INN: {$legalData['inn']}) in {$legalData['address']}");

                // STEP 3: VK Verification (Active Group)
                // Use cleaned name for better search context
                $cleanName = $this->cleanName($candidateName);
                $cleanLegal = $this->cleanName($legalData['name']);

                // Prefer DaData website, fall back to GPT website
                $website = $legalData['website'] ?? $candidateWebsite;

                $this->info("   -> [?] Debug: CleanName='{$cleanName}', Website='{$website}'");

                $vkUrl = $vk->findGroup($cleanName, $website, $cleanLegal, $legalData['address']);

                if (!$vkUrl) {
                    $this->warn("   -> [X] Step 3 Failed: No VK group found.");
                    continue;
                }

                // Analyze group activity
                $analysis = $vk->analyzeGroup($vkUrl);
                if (($analysis['vk_status'] ?? 'UNKNOWN') === 'DEAD') {
                    $this->warn("   -> [X] Step 3 Failed: VK Group is DEAD/Inactive.");
                    continue;
                }

                $this->info("   -> [V] Step 3 Passed: Active Group Found ($vkUrl)");

                // SAVE TO DB
                if ($this->saveCompany($candidateName, $legalData, $vkUrl, $industry)) {
                    $addedCount++;
                }
            }

            // Safety break if GPT keeps giving bad results
            if (count($processedNames) > 200 && $addedCount == 0) {
                $this->error("Too many failures. Stopping to save API quota.");
                break;
            }
        }

        $this->newLine();
        $this->info("=== Done! Added $addedCount new high-quality leads. ===");
    }

    private function askGpt(YandexGPTService $gpt, string $industry, array $exclude = []): array
    {
        $excludeText = "";
        if (!empty($exclude)) {
            // Only send last 50 exclusions to save tokens
            $recentExcludes = array_slice($exclude, -50);
            $excludeText = "ИСКЛЮЧИ из поиска эти компании: " . implode(", ", $recentExcludes) . ". ";
        }

        $prompt = "Предложи список из 50 российских компаний в сфере '{$industry}', которые могут быть потенциальными клиентами диджитал-агентства. " .
            "ВАЖНО: Исключи международные бренды. Приоритет: Российские производители, дистрибьюторы в МОСКВЕ. " .
            $excludeText .
            "Верни ТОЛЬКО валидный JSON массив объектов с официальным сайтом, например: " .
            "[{ \"name\": \"Название\", \"website\": \"site.ru\" }]. Без лишнего текста.";

        $result = $gpt->search($prompt);
        $content = $result['content'] ?? '';

        // Extract JSON
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $data = json_decode($matches[0], true);
            $candidates = is_array($data) ? $data : [];

            // Normalize to array of objects or strings (handling potential legacy/hallucinated formats)
            return array_map(function ($item) {
                if (is_string($item))
                    return ['name' => $item, 'website' => null];
                return [
                    'name' => $item['name'] ?? 'Unknown',
                    'website' => $item['website'] ?? null
                ];
            }, $candidates);
        }

        return [];
    }

    private function checkDaData(string $query): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->dadataKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party', [
                        'query' => $query,
                        'count' => 1,
                        'status' => ['ACTIVE'],
                        'locations' => [
                            ['region' => 'Москва'],
                            ['region_iso_code' => 'RU-MOS'] // Moscow Region
                        ]
                    ]);

            $suggestions = $response->json()['suggestions'] ?? [];

            if (empty($suggestions)) {
                // Try searching explicitly by region codes 77 and 50
                $response = Http::withHeaders([
                    'Authorization' => 'Token ' . $this->dadataKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party', [
                            'query' => $query,
                            'count' => 1,
                            'status' => ['ACTIVE'],
                            'locations' => [
                                ['region_code' => '77'],
                                ['region_code' => '50']
                            ]
                        ]);
                $suggestions = $response->json()['suggestions'] ?? [];
            }

            if (empty($suggestions))
                return null;

            $data = $suggestions[0]['data'];
            $address = $data['address']['value'] ?? '';

            // Double check Moscow or Region
            if (!str_contains($address, 'Москва') && !str_contains($address, '77') && !str_contains($address, 'Московская') && !str_contains($address, '50')) {
                return null;
            }

            return [
                'name' => $data['name']['short_with_opf'] ?? $suggestions[0]['value'],
                'inn' => $data['inn'],
                'address' => $address,
                'ogrn' => $data['ogrn'] ?? null,
                'okved' => $data['okved'] ?? null,
                'management_name' => $data['management']['name'] ?? null,
                'management_post' => $data['management']['post'] ?? null,
                'website' => $data['url'] ?? null,
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    private function cleanName(string $name): string
    {
        // Remove legal forms
        $name = preg_replace('/(ООО|АО|ПАО|ЗАО|ИП)\s+/iu', '', $name);
        // Remove quotes and special chars
        $name = preg_replace('/["«»]/u', '', $name);
        return trim($name);
    }

    private function saveCompany(string $name, array $legalData, string $vkUrl, string $industry): bool
    {
        if (Company::where('inn', $legalData['inn'])->exists()) {
            $this->warn("   -> [!] Skipped (Already exists by INN)");
            return false;
        }

        $team = Team::first();
        if (!$team) {
            $this->error("No team found to assign company.");
            return false;
        }

        Company::create([
            'team_id' => $team->id,
            'name' => $name,
            'legal_name' => $legalData['name'],
            'inn' => $legalData['inn'],
            'address_line_1' => $legalData['address'],
            'vk_url' => $vkUrl,
            'industry' => $industry,
            'status' => 'LEAD',
            'lead_category' => 'COLD', // Default
            'creation_source' => 'AI_GENERATED',
            'ogrn' => $legalData['ogrn'],
            'okved' => $legalData['okved'],
            'management_name' => $legalData['management_name'],
            'management_post' => $legalData['management_post'],
            'smm_analysis_date' => now(), // Mark as analyzed recently
        ]);

        $this->info("   -> [+] Added to Database!");
        return true;
    }
}
