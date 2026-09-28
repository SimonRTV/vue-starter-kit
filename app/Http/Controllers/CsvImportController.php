<?php

namespace App\Http\Controllers;

use App\Actions\Exports\ExportCsv;
use App\Actions\Imports\ImportCsv;
use App\Actions\Imports\ImportRegistry;
use App\Actions\Imports\ReadCsv;
use App\Http\Requests\ConfirmCsvImportRequest;
use App\Http\Requests\PreviewCsvImportRequest;
use App\Http\Requests\UploadCsvRequest;
use App\Models\CsvImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportController extends Controller
{
    public function __construct(private ImportRegistry $registry) {}

    public function create(string $resource): Response
    {
        $this->registry->get($resource)->authorize();

        return $this->render($resource);
    }

    public function store(UploadCsvRequest $request, string $resource, ReadCsv $reader): RedirectResponse
    {
        $this->registry->get($resource)->authorize();
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $delimiter = $request->string('delimiter')->toString();
        $document = $reader->handle($file, $delimiter === 'tab' ? "\t" : $delimiter);
        $fields = $this->registry->get($resource)->definition()['fields'];
        $mapping = array_fill_keys(array_keys($fields), null);
        foreach ($document['headers'] as $index => $header) {
            foreach ($fields as $key => $label) {
                if (in_array(Str::lower(Str::ascii($header)), [$key, Str::lower(Str::ascii($label))], true)) {
                    $mapping[$key] = $index;
                }
            }
        }
        $import = CsvImport::query()->create([
            'user_id' => $request->user()->id, 'resource' => $resource, 'document' => $document,
            'mapping' => $mapping, 'expires_at' => now()->addMinutes(30),
        ]);

        return to_route('csv-imports.show', ['resource' => $resource, 'csvImport' => $import]);
    }

    public function show(string $resource, CsvImport $csvImport): Response
    {
        (new ImportCsv($this->registry->get($resource)))->authorizeImport($csvImport);

        return $this->render($resource, $csvImport);
    }

    public function preview(PreviewCsvImportRequest $request, string $resource, CsvImport $csvImport): RedirectResponse
    {
        /** @var array<string, int|string|null> $input */
        $input = $request->validated('mapping');
        $mapping = array_map(fn (int|string|null $value): ?int => $value === null ? null : (int) $value, $input);
        (new ImportCsv($this->registry->get($resource)))->preview($csvImport, $mapping, $request->string('duplicate_mode')->toString());

        return to_route('csv-imports.show', ['resource' => $resource, 'csvImport' => $csvImport]);
    }

    public function commit(ConfirmCsvImportRequest $request, string $resource, CsvImport $csvImport): RedirectResponse
    {
        (new ImportCsv($this->registry->get($resource)))->commit($csvImport, $request->string('preview_token')->toString());

        return to_route('csv-imports.show', ['resource' => $resource, 'csvImport' => $csvImport]);
    }

    public function errors(string $resource, CsvImport $csvImport, ExportCsv $csv): StreamedResponse
    {
        (new ImportCsv($this->registry->get($resource)))->authorizeImport($csvImport);
        abort_if($csvImport->preview === null || $csvImport->document === null, 404);
        $headers = $csvImport->document['headers'];
        $width = max(count($headers), ...array_map(fn (array $row): int => count($row['cells']), $csvImport->document['rows']));
        while (count($headers) < $width) {
            $headers[] = 'Colonne supplémentaire '.(count($headers) + 1);
        }
        $rows = [];
        foreach ($csvImport->preview['rows'] as $index => $row) {
            if ($row['errors'] !== []) {
                $cells = array_pad($csvImport->document['rows'][$index]['cells'], $width, '');
                $rows[] = [$row['number'], ...$cells, implode(' ', $row['errors'])];
            }
        }

        return $csv->download($rows, 'erreurs-import-'.$resource.'.csv', ['Ligne', ...$headers, 'Erreurs']);
    }

    public function destroy(string $resource, CsvImport $csvImport): RedirectResponse
    {
        (new ImportCsv($this->registry->get($resource)))->authorizeImport($csvImport);
        $csvImport->delete();

        return to_route('csv-imports.create', ['resource' => $resource]);
    }

    private function render(string $resource, ?CsvImport $import = null): Response
    {
        $definition = $this->registry->get($resource)->definition();
        $fields = $definition['fields'];
        Head::title('Importer — '.$definition['label']);

        return Inertia::render('imports/Import', [
            'resource' => $resource, 'label' => $definition['label'], 'columns' => $definition['columns'],
            'mappingHelp' => $definition['help'], 'returnUrl' => route($definition['indexRoute']),
            'fields' => collect($fields)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label, 'required' => in_array($key, $definition['required'], true)])->values()->all(),
            'canUpdate' => auth()->user()?->can($definition['updatePermission']) ?? false,
            'batch' => $import === null ? null : [
                'id' => $import->id, 'headers' => $import->document['headers'] ?? [],
                'samples' => array_map(fn (array $row): array => array_map(fn (string $cell): string => Str::limit($cell, 120), $row['cells']), array_slice($import->document['rows'] ?? [], 0, 3)),
                'total' => count($import->document['rows'] ?? []), 'mapping' => $import->mapping,
                'duplicateMode' => $import->duplicate_mode, 'expiresAt' => $import->expires_at->toISOString(),
                'completed' => $import->completed_at !== null, 'previewToken' => $import->preview_token,
                'preview' => $import->preview === null ? null : [
                    'counts' => $import->preview['counts'],
                    'rows' => array_map(fn (array $row): array => [
                        'number' => $row['number'], 'cells' => array_map(fn (string $cell): string => Str::limit($cell, 160), $row['cells']),
                        'action' => $row['action'], 'errors' => $row['errors'],
                    ], array_slice($import->preview['rows'], 0, 100)),
                ],
            ],
        ]);
    }
}
