<?php

namespace App\Models;

use App\Concerns\HasMediaAttachments;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, string>|null $seo
 * @property array{title: string, slug: string, excerpt: string|null, body: string|null, body_format: string}|null $draft
 * @property int|null $page_template_id
 * @property array<string, mixed>|null $template_fields
 * @property-read PageTemplate|null $template
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body_format
 * @property string|null $body
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'slug', 'excerpt', 'body', 'body_format', 'is_published', 'published_at', 'seo', 'page_template_id', 'template_fields', 'draft'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasMediaAttachments;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_published' => false,
        'body_format' => 'text',
    ];

    /** @return BelongsTo<PageTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PageTemplate::class, 'page_template_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'seo' => 'array',
            'template_fields' => 'array',
            'draft' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
