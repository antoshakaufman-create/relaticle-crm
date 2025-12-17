<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\VkActionService;
use Illuminate\Console\Command;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ExportVkAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-vk-analysis {--source= : Filter by creation_source (e.g. AI_GENERATED)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze all companies with VK URLs and export results to Excel';

    /**
     * Execute the console command.
     */
    public function handle(VkActionService $vkService)
    {
        $this->info("Starting VK Analysis Export...");

        $source = $this->option('source');

        $query = Company::whereNotNull('vk_url')->where('vk_url', '!=', '');

        if ($source) {
            $query->where('creation_source', $source);
            $this->info("Filtering by source: $source");
        }

        $companies = $query->get();
        $total = $companies->count();

        $this->info("Found {$total} companies to analyze.");

        $fileName = 'vk_analysis_report.xlsx';
        $filePath = base_path($fileName);

        $writer = new Writer();
        $writer->openToFile($filePath);

        // Header
        $header = [
            'ID',
            'Company Name',
            'VK URL',
            'Status',
            'Score',
            'Category',
            'ER Score',
            'Posts/Month',
            'Managers (Format: Name (Role) [Link])',
            'Managers Email',
            'Managers Phone',
            'Last Analysis Date'
        ];
        $writer->addRow(Row::fromValues($header));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($companies as $company) {
            try {
                // Run Analysis
                $result = $vkService->analyzeGroup($company->vk_url);

                if (isset($result['error'])) {
                    $row = [
                        $company->id,
                        $company->name,
                        $company->vk_url,
                        'ERROR: ' . $result['error'],
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        now()->toDateTimeString()
                    ];
                } else {
                    // Update Company DB Record (optional but good for consistency)
                    $company->update([
                        'smm_analysis' => $result['smm_analysis'],
                        'vk_status' => $result['vk_status'],
                        'er_score' => $result['er_score'],
                        'posts_per_month' => $result['posts_per_month'],
                        'lead_score' => $result['lead_score'],
                        'lead_category' => $result['lead_category'],
                        'smm_analysis_date' => now(),
                    ]);

                    // Format Contacts
                    $contactsList = $result['contacts_data'] ?? [];
                    $managersStr = [];
                    $emailsStr = [];
                    $phonesStr = [];

                    foreach ($contactsList as $c) {
                        $managersStr[] = "{$c['name']} ({$c['title']}) [{$c['link']}]";
                        if (!empty($c['email']))
                            $emailsStr[] = "{$c['name']}: {$c['email']}";
                        if (!empty($c['phone']))
                            $phonesStr[] = "{$c['name']}: {$c['phone']}";
                    }

                    $row = [
                        $company->id,
                        $company->name,
                        $company->vk_url,
                        $result['vk_status'],
                        $result['lead_score'],
                        $result['lead_category'],
                        number_format($result['er_score'], 2) . '%',
                        $result['posts_per_month'],
                        implode(";\n", $managersStr),
                        implode("; ", $emailsStr),
                        implode("; ", $phonesStr),
                        now()->toDateTimeString()
                    ];
                }

                $writer->addRow(Row::fromValues($row));

            } catch (\Exception $e) {
                // Log and continue
                $row = [
                    $company->id,
                    $company->name,
                    $company->vk_url,
                    'EXCEPTION: ' . $e->getMessage(),
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    now()->toDateTimeString()
                ];
                $writer->addRow(Row::fromValues($row));
            }

            $bar->advance();
            // Sleep slightly to avoid strict rate limits if many
            usleep(200000); // 0.2s
        }

        $bar->finish();
        $writer->close();

        $this->newLine();
        $this->info("Export completed! File saved to: {$filePath}");
    }
}
