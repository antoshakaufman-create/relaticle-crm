<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Person;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AnalyzeContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:analyze-contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze contacts for rebranding/acquisition cases using YandexGPT';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting Contact Analysis...");

        $apiKey = config('ai.yandex.api_key');
        $folderId = config('ai.yandex.folder_id');
        $baseUrl = config('ai.yandex.base_url');

        if (!$apiKey || !$folderId) {
            $this->error("YandexGPT not configured.");
            return;
        }

        // 1. Get Unique Companies involved
        // We'll look at 'companies' table and 'people' table (email domains)

        $companies = Company::pluck('name')->unique()->filter()->values()->toArray();
        $this->info("Found " . count($companies) . " companies in database.");

        // For this task, let's focus on the user's specific request: finding "interesting" cases like KazanExpress
        // We will iterate companies and ask GPT.

        $report = [];
        $headers = ['Company Name', 'Status', 'GPT Comment'];
        $report[] = $headers;

        $bar = $this->output->createProgressBar(count($companies));
        $bar->start();

        foreach ($companies as $companyName) {
            // Skip short names or obviously generic ones
            if (mb_strlen($companyName) < 3)
                continue;

            $prompt = "Компания '$companyName' в России. Знаешь ли ты о ребрендинге, покупке этой компании или слиянии с другой (например, как KazanExpress стал Магнит Маркет)? Если да, напиши коротко 'ДА: <пояснение>' и укажи новый актуальный сайт/название. Если нет или это обычная компания, ответь 'НЕТ'.";

            try {
                $response = Http::timeout(5) // Fast timeout, we have many
                    ->withHeaders([
                        'Authorization' => 'Api-Key ' . $apiKey,
                        'x-folder-id' => $folderId,
                    ])
                    ->post($baseUrl . '/completion', [
                        'modelUri' => "gpt://{$folderId}/yandexgpt/latest",
                        'completionOptions' => [
                            'stream' => false,
                            'temperature' => 0.1, // Low temp for facts
                            'maxTokens' => 200,
                        ],
                        'messages' => [
                            ['role' => 'user', 'text' => $prompt],
                        ],
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['result']['alternatives'][0]['message']['text'] ?? '';

                    if (stripos($text, 'ДА:') !== false) {
                        // Clean up text
                        $comment = trim(str_ireplace('ДА:', '', $text));
                        $report[] = [$companyName, 'INTERESTING', $comment];
                        $this->line(" [!] Found: $companyName -> $comment");
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }

            $bar->advance();
            usleep(100000); // 100ms
        }

        $bar->finish();
        $this->newLine();

        // Save CSV
        $fp = fopen(base_path('rebranding_report.csv'), 'w');
        foreach ($report as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);

        $this->info("Analysis complete. Saved to rebranding_report.csv");
    }
}
