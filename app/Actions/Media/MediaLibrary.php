<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class MediaLibrary
{
    /** @return LengthAwarePaginator<int, Media> */
    public function search(User $user, string $search, ?string $visibility): LengthAwarePaginator
    {
        return Media::query()
            ->when(! $user->can(MediaPolicy::MANAGE_ALL), fn ($query) => $query->where('uploaded_by', $user->id))
            ->when($search !== '', fn ($query) => $query->whereLike('title', '%'.$search.'%'))
            ->when($visibility !== null && $visibility !== '', fn ($query) => $query->where('visibility', $visibility))
            ->latest()->orderByDesc('id')->paginate(24)->withQueryString();
    }

    /** @return array{id: string, title: string, original_name: string, mime_type: string, size: int, alt_text: string|null, visibility: string, download_url: string, thumbnail_url: string|null, can: array{update: bool, delete: bool}} */
    public function item(Media $media): array
    {
        return [
            'id' => $media->id, 'title' => $media->title, 'original_name' => $media->original_name,
            'mime_type' => $media->mime_type, 'size' => $media->size,
            'alt_text' => $media->alt_text, 'visibility' => $media->visibility,
            'download_url' => route('media-files.download', $media),
            'thumbnail_url' => $media->thumbnail_path !== null ? route('media-files.thumbnail', $media) : null,
            'can' => ['update' => Gate::allows('update', $media), 'delete' => Gate::allows('delete', $media)],
        ];
    }
}
