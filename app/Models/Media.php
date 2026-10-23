<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $folder_id
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
 * @property list<string>|null $tags
 * @property Carbon|null $created_at
 */
#[Fillable(['id', 'folder_id', 'uploaded_by', 'title', 'original_name', 'mime_type', 'size', 'disk', 'path', 'thumbnail_path', 'alt_text', 'visibility', 'tags'])]
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
        return ['size' => 'integer', 'tags' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<MediaFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /** @return MorphToMany<Page, $this> */
    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'attachable', 'media_attachments')->withTrashed();
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, config('media.image_mimes'), true);
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Whether the file can be played inline by the browser's media elements.
     */
    public function isPlayable(): bool
    {
        return $this->isAudio() || $this->isVideo();
    }
}
