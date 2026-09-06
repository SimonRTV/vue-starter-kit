<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $uploaded_by
 * @property string $title
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $disk
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string|null $alt_text
 * @property string $visibility
 * @property Carbon|null $created_at
 */
#[Fillable(['id', 'uploaded_by', 'title', 'original_name', 'mime_type', 'size', 'disk', 'path', 'thumbnail_path', 'alt_text', 'visibility'])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use HasUuids;

    /** @var array<string, string> */
    protected $attributes = ['visibility' => 'private'];

    /** @var list<string> */
    protected $hidden = ['disk', 'path', 'thumbnail_path'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
