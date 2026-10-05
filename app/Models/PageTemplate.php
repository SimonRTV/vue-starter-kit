<?php

namespace App\Models;

use Database\Factories\PageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $renderer
 * @property-read Collection<int, PageFieldSet> $fieldSets
 */
#[Fillable(['name', 'renderer'])]
class PageTemplate extends Model
{
    /** @use HasFactory<PageTemplateFactory> */
    use HasFactory;

    /** @return BelongsToMany<PageFieldSet, $this> */
    public function fieldSets(): BelongsToMany
    {
        return $this->belongsToMany(PageFieldSet::class)->withPivot('position')->orderByPivot('position');
    }

    /** @return HasMany<Page, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
