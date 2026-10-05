<?php

namespace App\Models;

use Database\Factories\PageFieldSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @phpstan-type FieldDefinition array{key: string, label: string, type: string, required: bool, options?: list<string>}
 *
 * @property int $id
 * @property string $name
 * @property string $key
 * @property list<FieldDefinition> $fields
 */
#[Fillable(['name', 'key', 'fields'])]
class PageFieldSet extends Model
{
    /** @use HasFactory<PageFieldSetFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fields' => 'array'];
    }

    /** @return BelongsToMany<PageTemplate, $this> */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(PageTemplate::class);
    }
}
