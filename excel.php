<?php

declare(strict_types=1);

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    throw new RuntimeException('Missing dependencies. Run composer install first.');
}

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * @return array<int, array<int, mixed>>
 */
function readSpreadsheetRows(string $tmpPath, string $originalName): array
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === 'csv') {
        $rows = [];
        if (($handle = fopen($tmpPath, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }

    $spreadsheet = IOFactory::load($tmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    return $sheet->toArray(null, true, true, false);
}
