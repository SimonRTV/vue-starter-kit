<?php

namespace App\Actions\Imports;

use App\Models\CsvImport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type ImportRow from ImportResource
 *
 * @phpstan-type ImportPreview array{rows: list<ImportRow>, counts: array{create: int, update: int, skip: int, error: int}}
 */
class ImportCsv
{
    public function __construct(public ImportResource $resource) {}

    public function authorizeImport(CsvImport $import): void
    {
        $this->resource->authorize();
        abort_unless($import->user_id === auth()->id() && $import->resource === $this->resource->definition()['key'], 404);
        abort_if($import->expires_at->isPast(), 410, 'Cet import a expiré. Chargez à nouveau le fichier.');
    }

    /** @param array<string, int|null> $mapping */
    public function preview(CsvImport $import, array $mapping, string $mode): void
    {
        DB::transaction(function () use ($import, $mapping, $mode): void {
            $locked = CsvImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->authorizeImport($locked);
            abort_if($locked->completed_at !== null, 409);
            $indices = array_values(array_filter($mapping, fn (?int $index): bool => $index !== null));
            if (count(array_unique($indices)) !== count($indices)
                || count(array_filter($indices, fn (int $index): bool => ! isset($locked->document['headers'][$index]))) > 0) {
                throw ValidationException::withMessages(['mapping' => 'Associez chaque champ à une colonne distincte du fichier.']);
            }
            $locked->mapping = $mapping;
            $locked->duplicate_mode = $mode;
            $locked->preview = $this->evaluate($locked);
            $locked->preview_token = (string) Str::uuid();
            $locked->save();
        });
    }

    public function commit(CsvImport $import, string $token): void
    {
        try {
            DB::transaction(function () use ($import, $token): void {
                $locked = CsvImport::query()->lockForUpdate()->findOrFail($import->id);
                $this->authorizeImport($locked);
                if ($locked->completed_at !== null) {
                    return;
                }
                if ($locked->preview === null || $locked->preview_token !== $token) {
                    throw ValidationException::withMessages(['preview_token' => 'Générez un nouvel aperçu avant de confirmer.']);
                }
                $current = $this->evaluate($locked, lock: true);
                if ($current['counts']['error'] > 0) {
                    throw ValidationException::withMessages(['preview_token' => 'Corrigez les erreurs et générez un nouvel aperçu. Aucune ligne n’a été importée.']);
                }
                if ($current !== $locked->preview) {
                    throw ValidationException::withMessages(['preview_token' => 'Des données ont changé depuis l’aperçu. Vérifiez un nouvel aperçu avant de confirmer.']);
                }
                foreach ($current['rows'] as $row) {
                    if ($row['action'] !== 'skip') {
                        $this->resource->apply($row);
                    }
                }
                $locked->update(['completed_at' => now(), 'document' => null, 'preview' => ['rows' => [], 'counts' => $current['counts']]]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['preview_token' => 'Un identifiant existe déjà ou vient d’être créé. Vérifiez les doublons et générez un nouvel aperçu. Aucune ligne n’a été importée.']);
        }
    }

    /** @return ImportPreview */
    public function evaluate(CsvImport $import, bool $lock = false): array
    {
        $document = $import->document;
        abort_if($document === null, 409);
        $rows = [];
        $seen = [];
        $counts = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];
        foreach ($document['rows'] as $row) {
            $mapped = [];
            foreach ($import->mapping as $field => $index) {
                if ($index !== null) {
                    $mapped[$field] = trim($row['cells'][$index] ?? '');
                }
            }
            $prepared = $this->resource->prepare($mapped, $lock, $import->duplicate_mode);
            if (count($row['cells']) !== count($document['headers'])) {
                $prepared['errors'][] = 'Le nombre de cellules ne correspond pas aux en-têtes.';
            }
            if (isset($seen[$prepared['identity']])) {
                $prepared['errors'][] = 'Identifiant répété dans le fichier (ligne '.$seen[$prepared['identity']].').';
            }
            $seen[$prepared['identity']] = $row['number'];
            $action = $prepared['existing_id'] === null ? 'create' : ($import->duplicate_mode === 'update' ? 'update' : 'skip');
            if ($prepared['errors'] !== []) {
                $action = 'error';
            }
            $counts[$action]++;
            $rows[] = ['number' => $row['number'], ...$prepared, 'action' => $action];
        }

        return ['rows' => $rows, 'counts' => $counts];
    }
}
