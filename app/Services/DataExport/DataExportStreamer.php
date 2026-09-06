<?php

namespace App\Services\DataExport;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a dataset straight to the browser so exports never have to fit in memory.
 */
class DataExportStreamer
{
    public const FORMATS = [
        'csv' => 'CSV (Excel & Google Sheets)',
        'json' => 'JSON',
    ];

    protected const FLUSH_EVERY = 500;

    public function download(DataExportDefinition $definition, DataExportFilters $filters, string $format = 'csv'): StreamedResponse
    {
        $format = array_key_exists($format, self::FORMATS) ? $format : 'csv';

        return response()->streamDownload(
            fn () => $format === 'json'
                ? $this->writeJson($definition, $filters)
                : $this->writeCsv($definition, $filters),
            $definition->filename($format),
            [
                'Content-Type' => $format === 'json' ? 'application/json' : 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    protected function writeCsv(DataExportDefinition $definition, DataExportFilters $filters): void
    {
        $handle = fopen('php://output', 'wb');

        // Excel only detects UTF-8 CSV files when they start with a byte order mark.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $definition->headers());

        $keys = $definition->columnKeys();
        $written = 0;

        foreach ($definition->rows($filters) as $row) {
            fputcsv($handle, array_map(
                fn (string $key): string => DataExportValue::text($row[$key] ?? ''),
                $keys,
            ));

            if (++$written % self::FLUSH_EVERY === 0) {
                fflush($handle);
            }
        }

        fclose($handle);
    }

    protected function writeJson(DataExportDefinition $definition, DataExportFilters $filters): void
    {
        echo '[';

        $written = 0;

        foreach ($definition->rows($filters) as $row) {
            echo $written === 0 ? "\n" : ",\n";
            echo '    '.json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if (++$written % self::FLUSH_EVERY === 0) {
                flush();
            }
        }

        echo $written === 0 ? "]\n" : "\n]\n";
    }
}
