<?php

namespace App\Actions\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type CsvRow array{number: int, cells: list<string>}
 * @phpstan-type CsvDocument array{headers: list<string>, rows: list<CsvRow>}
 */
class ReadCsv
{
    public const MAX_ROWS = 1000;

    /** @return CsvDocument */
    public function handle(UploadedFile $file, string $delimiter): array
    {
        $contents = $file->get();
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            throw ValidationException::withMessages(['file' => 'Utilisez un fichier CSV encodé en UTF-8.']);
        }
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $this->validateQuotes($contents, $delimiter);
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'Impossible de lire ce fichier.']);
        }
        try {
            fwrite($stream, $contents);
            rewind($stream);
            $headers = fgetcsv($stream, null, $delimiter, '"', '');
            if ($headers === false || $headers === [null]) {
                throw ValidationException::withMessages(['file' => 'Le fichier doit contenir une ligne d’en-têtes.']);
            }
            $headers = array_map(fn (?string $value): string => trim($value ?? ''), $headers);
            if (count($headers) > 50 || in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)
                || count(array_filter($headers, fn (string $header): bool => mb_strlen($header) > 100)) > 0) {
                throw ValidationException::withMessages(['file' => 'Utilisez au maximum 50 en-têtes distincts, non vides, de 100 caractères maximum. Vérifiez le séparateur.']);
            }
            $rows = [];
            $number = 1;
            while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
                $number++;
                if ($cells === [null]) {
                    continue;
                }
                if (count($cells) > 50) {
                    throw ValidationException::withMessages(['file' => 'Une ligne contient plus de 50 cellules. Vérifiez le séparateur et les guillemets.']);
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'Le fichier dépasse la limite de 1 000 lignes. Scindez-le en plusieurs fichiers.']);
                }
                $rows[] = ['number' => $number, 'cells' => array_map(fn (?string $cell): string => $cell ?? '', $cells)];
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['file' => 'Le fichier ne contient aucune donnée.']);
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            fclose($stream);
        }
    }

    private function validateQuotes(string $contents, string $delimiter): void
    {
        $state = 'start';
        $length = strlen($contents);
        for ($index = 0; $index < $length; $index++) {
            $character = $contents[$index];
            if ($state === 'quoted') {
                if ($character === '"') {
                    if (($contents[$index + 1] ?? '') === '"') {
                        $index++;
                    } else {
                        $state = 'closed';
                    }
                }
            } elseif ($character === $delimiter || $character === "\n" || $character === "\r") {
                $state = 'start';
            } elseif ($character === '"' && $state === 'start') {
                $state = 'quoted';
            } elseif ($state === 'closed' || $character === '"') {
                throw ValidationException::withMessages(['file' => 'Guillemets CSV invalides. Entourez la cellule de guillemets et doublez les guillemets intérieurs.']);
            } else {
                $state = 'text';
            }
        }
        if ($state === 'quoted') {
            throw ValidationException::withMessages(['file' => 'Une cellule contient un guillemet non fermé.']);
        }
    }
}
