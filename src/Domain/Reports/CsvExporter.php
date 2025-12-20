<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use function array_keys;
use function fclose;
use function fopen;
use function fputcsv;
use function fwrite;
use function is_string;
use function rewind;
use function stream_get_contents;

/**
 * Gestisce l'export di dati in formato CSV.
 * Estratto da Service.php per migliorare modularità.
 */
final class CsvExporter
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<string, string>> $rows
     */
    public function buildCsv(array $headers, array $rows, string $delimiter, bool $withBom): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        if ($withBom) {
            fwrite($handle, "\xEF\xBB\xBF");
        }

        fputcsv($handle, $headers, $delimiter);

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }

            fputcsv($handle, $line, $delimiter);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }
}
















