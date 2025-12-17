<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\VkActionService;
use Illuminate\Console\Command;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ExportVkManagers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-vk-managers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export list of VK Community Managers to Excel (Row per Person)';

    /**
     * Execute the console command.
     */
    public function handle(VkActionService $vkService)
    {
        $this->info("Starting VK Managers Discovery Export...");

        $companies = Company::whereNotNull('vk_url')->where('vk_url', '!=', '')->get();
        $total = $companies->count();

        $this->info("Found {$total} companies to scan.");

        $fileName = 'vk_managers.xlsx';
        $filePath = base_path($fileName);

        $writer = new Writer();
        $writer->openToFile($filePath);

        // Header
        $header = [
            'Company ID',
            'Company Name',
            'Company VK',
            'Manager Name',
            'Position/Role',
            'VK Profile',
            'Email',
            'Phone',
            'Source'
        ];
        $writer->addRow(Row::fromValues($header));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $managersFound = 0;

        foreach ($companies as $company) {
            try {
                // Run Analysis
                $result = $vkService->analyzeGroup($company->vk_url);

                if (!isset($result['error'])) {
                    $contacts = $result['contacts_data'] ?? [];

                    if (empty($contacts)) {
                        // Optional: Write a row saying "No managers found" if you want full coverage, 
                        // but usually "Export Managers" implies only finding people.
                        // Let's skip empty ones to strictly provide a list of people.
                    }

                    foreach ($contacts as $person) {
                        $managersFound++;
                        $row = [
                            $company->id,
                            $company->name,
                            $company->vk_url,
                            $person['name'],
                            $person['title'], // Role + Description
                            $person['link'],
                            $person['email'] ?? '',
                            $person['phone'] ?? '',
                            'VK Analysis'
                        ];
                        $writer->addRow(Row::fromValues($row));
                    }
                }

            } catch (\Exception $e) {
                // Log silently
            }

            $bar->advance();
            // Sleep slightly
            usleep(250000); // 0.25s
        }

        $bar->finish();
        $writer->close();

        $this->newLine();
        $this->info("Export completed! Found {$managersFound} managers.");
        $this->info("File saved to: {$filePath}");
    }
}
