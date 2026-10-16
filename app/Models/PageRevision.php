<?php

namespace App\Models;

use Database\Factories\PageRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type Content array{title: string, slug: string, excerpt: string|null, body: string|null, body_format: string, page_template_id: int|null, template_fields: array<string, mixed>|null}
 *
 * @property int $id
 * @property int $page_id
 * @property int|null $user_id
 * @property string|null $author_name
 * @property string $event
 * @property bool $is_published
 * @property Content $content
 * @property Carbon|null $created_at
 */
#[Fillable(['page_id', 'user_id', 'author_name', 'event', 'is_published', 'content', 'created_at'])]
class PageRevision extends Model
{
    /** @use HasFactory<PageRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const CONTENT_FIELDS = ['title', 'slug', 'excerpt', 'body', 'body_format', 'page_template_id', 'template_fields'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_published' => false];

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['content' => 'array', 'is_published' => 'boolean'];
    }
}
