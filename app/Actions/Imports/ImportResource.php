<?php

namespace App\Actions\Imports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * @phpstan-type ImportDefinition array{key: string, label: string, fields: array<string, string>, required: list<string>, columns: list<string>, help: string, model: class-string<Model>, updatePermission: string, feature: string|null, indexRoute: string}
 * @phpstan-type PreparedRow array{identity: string, attributes: array<string, mixed>, errors: list<string>, existing_id: int|null, fingerprint: string|null, cells: list<string>}
 * @phpstan-type ImportRow array{number: int, identity: string, attributes: array<string, mixed>, action: string, errors: list<string>, existing_id: int|null, fingerprint: string|null, cells: list<string>}
 */
abstract class ImportResource
{
    /** @return ImportDefinition */
    abstract public function definition(): array;

    /**
     * @param  array<string, string>  $mapped
     * @return PreparedRow
     */
    abstract public function prepare(array $mapped, bool $lock, string $mode): array;

    /** @param ImportRow $row */
    abstract public function apply(array $row): void;

    public function authorize(): void
    {
        $definition = $this->definition();
        abort_if($definition['feature'] !== null && ! config($definition['feature']), 404);
        Gate::authorize('viewAny', $definition['model']);
        Gate::authorize('create', $definition['model']);
    }
}
