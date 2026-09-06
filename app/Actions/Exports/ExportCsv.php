<?php

namespace App\Actions\Exports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCsv
{
    public const MAX_ROWS = 10000;

    /**
     * @param  iterable<list<string|int|float|bool|null>>  $rows
     * @param  list<string>  $headers
     */
    public function download(iterable $rows, string $filename, array $headers): StreamedResponse
    {
        $records = [];
        foreach ($rows as $row) {
            abort_if(count($records) >= self::MAX_ROWS, 422, 'Affinez les filtres pour exporter au maximum 10 000 lignes.');
            $records[] = $row;
        }

        return response()->streamDownload(function () use ($records, $headers): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ',', '"', '');
            foreach ($records as $record) {
                fputcsv($stream, array_map($this->safeCell(...), $record), ',', '"', '');
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function safeCell(string|int|float|bool|null $value): string
    {
        $text = (string) $value;

        return preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $text) || preg_match('/^[\t\r\n]/', $text) ? "'".$text : $text;
    }
}
