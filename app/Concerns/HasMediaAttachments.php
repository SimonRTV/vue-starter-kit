<?php

namespace App\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasMediaAttachments
{
    /** @return MorphToMany<Media, $this> */
    public function attachments(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'attachable', 'media_attachments')->withTimestamps();
    }
}
