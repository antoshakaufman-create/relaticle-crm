<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\Lead;
use App\Models\People;
use App\Services\ExcelParserService;

class SmartImportService
{
    public function __construct(
        protected ExcelParserService $parser,
        protected YandexGPTService $ai
    ) {
    }

    public function analyzeFile(string $filePath, string $targetModel): array
    {
        // 1. Parse Headers & Sample
        $rows = $this->parser->preview($filePath, 5);
        if (empty($rows)) {
            return [];
        }

        $headers = $rows[0];
        $samples = array_slice($rows, 1);

        // 2. Ask AI for Mapping
        $mapping = $this->ai->analyzeImportStructure($headers, $samples, $targetModel);

        return [
            'headers' => $headers,
            'mapping' => $mapping,
            'samples' => $samples,
        ];
    }

    public function processImport(string $filePath, string $targetModel, array $mapping): int
    {
        $count = 0;
        $headers = null;
        $modelClass = $this->getModelClass($targetModel);

        foreach ($this->parser->iterate($filePath) as $index => $row) {
            if ($index === 0) {
                // Determine header indices
                $headers = $row;
                continue;
            }

            // Map data
            $data = [];
            foreach ($row as $colIndex => $value) {
                $headerName = $headers[$colIndex] ?? null;
                if ($headerName && isset($mapping[$headerName]) && $mapping[$headerName]) {
                    $field = $mapping[$headerName];
                    $data[$field] = $value;
                }
            }

            if (!empty($data)) {
                $modelClass::create($data);
                $count++;
            }
        }

        return $count;
    }

    protected function getModelClass(string $target): string
    {
        return match ($target) {
            'Lead' => Lead::class,
            'People' => People::class,
            'Company' => Company::class,
            default => Lead::class,
        };
    }
}
