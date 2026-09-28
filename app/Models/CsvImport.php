<?php

namespace App\Models;

use App\Actions\Imports\ImportCsv;
use App\Actions\Imports\ReadCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @phpstan-import-type CsvDocument from ReadCsv
 * @phpstan-import-type ImportPreview from ImportCsv
 *
 * @property string $id
 * @property int $user_id
 * @property string $resource
 * @property CsvDocument|null $document
 * @property array<string, int|null> $mapping
 * @property string $duplicate_mode
 * @property ImportPreview|null $preview
 * @property string|null $preview_token
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 */
class CsvImport extends Model
{
    use HasUuids;
    use MassPrunable;

    protected $fillable = ['user_id', 'resource', 'document', 'mapping', 'duplicate_mode', 'preview', 'preview_token', 'expires_at', 'completed_at'];

    protected $attributes = ['duplicate_mode' => 'skip', 'mapping' => '{}'];

    protected $hidden = ['document', 'preview'];

    protected function casts(): array
    {
        return [
            'document' => 'encrypted:array',
            'mapping' => 'array',
            'preview' => 'encrypted:array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}
