<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Diagnostics;

use function array_map;
use function fputcsv;
use function fopen;
use function gmdate;
use function rewind;
use function sprintf;
use function stream_get_contents;

/**
 * Gestisce l'export dei log in formato CSV.
 * Estratto da Service.php per migliorare modularità.
 */
final class LogExporter
{
    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public function exportToCsv(array $result, string $channel): array
    {
        $columns = array_map(static function (array $column): string {
            return (string) ($column['label'] ?? $column['key']);
        }, $result['columns']);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [
                'filename'  => 'fp-resv-diagnostics.csv',
                'mime_type' => 'text/csv',
                'format'    => 'csv',
                'delimiter' => ';',
                'content'   => '',
            ];
        }

        fputcsv($stream, $columns, ';');

        foreach ($result['entries'] as $entry) {
            $row = [];
            foreach ($result['columns'] as $column) {
                $key   = (string) $column['key'];
                $row[] = isset($entry[$key]) ? $this->stringify($entry[$key]) : '';
            }

            fputcsv($stream, $row, ';');
        }

        rewind($stream);
        $content = stream_get_contents($stream);

        return [
            'filename'  => sprintf('fp-resv-%s-logs-%s.csv', $channel, gmdate('Ymd-His')),
            'mime_type' => 'text/csv',
            'format'    => 'csv',
            'delimiter' => ';',
            'content'   => is_string($content) ? $content : '',
        ];
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
















