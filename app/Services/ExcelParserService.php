<?php

namespace App\Services;

use OpenSpout\Reader\Common\Creator\ReaderEntityFactory;
use OpenSpout\Reader\ReaderInterface;

class ExcelParserService
{
    /**
     * Parse the first N rows of a file to understand its structure.
     *
     * @param string $filePath
     * @param int $limit
     * @return array
     */
    public function preview(string $filePath, int $limit = 5): array
    {
        $reader = $this->getReader($filePath);
        $reader->open($filePath);

        $rows = [];
        $count = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
                $count++;

                if ($count >= $limit) {
                    break 2;
                }
            }
        }

        $reader->close();

        return $rows;
    }

    /**
     * Iterate through the entire file yielding rows.
     *
     * @param string $filePath
     * @return \Generator
     */
    public function iterate(string $filePath): \Generator
    {
        $reader = $this->getReader($filePath);
        $reader->open($filePath);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                yield $row->toArray();
            }
        }

        $reader->close();
    }

    protected function getReader(string $filePath): ReaderInterface
    {
        if (str_ends_with(strtolower($filePath), '.csv')) {
            return ReaderEntityFactory::createCSVReader();
        }

        return ReaderEntityFactory::createXLSXReader();
    }
}
