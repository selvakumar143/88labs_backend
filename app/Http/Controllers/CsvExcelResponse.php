<?php

namespace App\Http\Controllers;

use App\Support\XlsxExport;

trait CsvExcelResponse
{
    /**
     * Return streaming response for CSV or Excel export.
     *
     * @param string $fileBase
     * @param string[] $headers
     * @param array<int, array<string, mixed>> $rows
     * @param string $format
     */
    private function exportCsvOrExcel(string $fileBase, array $headers, array $rows, string $format = 'csv')
    {
        $format = $format === 'excel' ? 'excel' : 'csv';

        if ($format === 'excel') {
            $xlsxRows = array_merge(
                [$headers],
                array_map(fn (array $row) => $this->mapRowValues($headers, $row), $rows)
            );

            $xlsx = XlsxExport::fromRows($xlsxRows);

            return response($xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileBase . '.xlsx"',
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
        }, $fileBase . '.csv', [
            'Content-Type' => 'text/csv',
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
