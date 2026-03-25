<?php

namespace App\Http\Controllers;

use App\Support\XlsxExport;
use Illuminate\Support\Str;

trait CsvExcelResponse
{
    /**
     * Return streaming response for CSV or Excel export.
     *
     * @param string $fileBase
     * @param string[] $headers
     * @param array<int, array<string, mixed>> $rows
     * @param string $format
     * @param string|null $modelClass
     */
    private function exportCsvOrExcel(string $fileBase, array $headers, array $rows, string $format = 'csv', ?string $modelClass = null)
    {
        $format = $format === 'excel' ? 'excel' : 'csv';

        $prefix = $modelClass
            ? Str::kebab(class_basename($modelClass))
            : trim($fileBase, '-_');

        if ($prefix === '') {
            $prefix = 'export';
        }

        $timestamp = now()->format('Ymd_His');
        $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '-', "{$prefix}-{$timestamp}");
        $safeBase = trim($safeBase, '-_');

        $fileName = $safeBase . ($format === 'excel' ? '.xlsx' : '.csv');

        if ($format === 'excel') {
            $xlsxRows = array_merge(
                [$headers],
                array_map(fn (array $row) => $this->mapRowValues($headers, $row), $rows)
            );

            $xlsx = XlsxExport::fromRows($xlsxRows);

            return response($xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $this->mapRowValues($headers, $row));
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Ensure row values are ordered to match the header list.
     *
     * @param string[] $headers
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function mapRowValues(array $headers, array $row): array
    {
        return array_map(fn (string $column) => (string) ($row[$column] ?? ''), $headers);
    }
}
