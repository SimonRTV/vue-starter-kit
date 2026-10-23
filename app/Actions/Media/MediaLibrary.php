<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * @phpstan-type MediaFilters array{search: string|null, type: 'image'|'audio'|'video'|'document'|null, visibility: 'private'|'public'|null, tag: string|null, folder: int|'none'|null, sort: 'created_at'|'title'|'size', direction: 'asc'|'desc', per_page: int}
 */
class MediaLibrary
{
    /**
     * @param  MediaFilters  $filters
     * @return LengthAwarePaginator<int, Media>
     */
    public function search(User $user, array $filters): LengthAwarePaginator
    {
        $imageMimes = config('media.image_mimes');

        return $this->scoped($user)
            ->with(['uploader:id,name', 'pages:pages.id,pages.title,pages.deleted_at'])
            ->when($filters['search'] !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('title', '%'.$filters['search'].'%')
                ->orWhereLike('original_name', '%'.$filters['search'].'%')
                ->orWhereLike('alt_text', '%'.$filters['search'].'%')))
            ->when($filters['type'] === 'image', fn (Builder $query) => $query->whereIn('mime_type', $imageMimes))
            ->when($filters['type'] === 'audio', fn (Builder $query) => $query->whereLike('mime_type', 'audio/%'))
            ->when($filters['type'] === 'video', fn (Builder $query) => $query->whereLike('mime_type', 'video/%'))
            ->when($filters['type'] === 'document', fn (Builder $query) => $query->whereNotIn('mime_type', $imageMimes)
                ->whereNotLike('mime_type', 'audio/%')->whereNotLike('mime_type', 'video/%'))
            ->when($filters['visibility'] !== null, fn (Builder $query) => $query->where('visibility', $filters['visibility']))
            ->when($filters['tag'] !== null, fn (Builder $query) => $query->whereJsonContains('tags', $filters['tag']))
            ->when($filters['folder'] === 'none', fn (Builder $query) => $query->whereNull('folder_id'))
            ->when(is_int($filters['folder']), fn (Builder $query) => $query->where('folder_id', $filters['folder']))
            ->orderBy($filters['sort'], $filters['direction'])->orderBy('id', $filters['direction'])
            ->paginate($filters['per_page'])->withQueryString();
    }

    /** @return list<string> */
    public function tags(User $user): array
    {
        return array_values($this->scoped($user)->whereNotNull('tags')->pluck('tags')
            ->flatten()->filter(fn (mixed $tag): bool => is_string($tag))
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->sort(fn (string $first, string $second): int => strnatcasecmp($first, $second))
            ->all());
    }

    /** @return array{count: int, size: int, unfiled: int} */
    public function stats(User $user): array
    {
        $query = $this->scoped($user);

        return [
            'count' => (clone $query)->count(), 'size' => (int) (clone $query)->sum('size'),
            'unfiled' => $query->whereNull('folder_id')->count(),
        ];
    }

    /** @return array{id: string, folder_id: int|null, title: string, original_name: string, mime_type: string, size: int, alt_text: string|null, visibility: string, tags: list<string>, is_image: bool, is_audio: bool, is_video: bool, created_at: string|null, uploader: string|null, download_url: string, stream_url: string|null, thumbnail_url: string|null, usages: list<array{id: int, title: string, url: string|null, trashed: bool}>, can: array{update: bool, delete: bool}} */
    public function item(Media $media): array
    {
        return [
            'id' => $media->id, 'folder_id' => $media->folder_id, 'title' => $media->title, 'original_name' => $media->original_name,
            'mime_type' => $media->mime_type, 'size' => $media->size,
            'alt_text' => $media->alt_text, 'visibility' => $media->visibility,
            'tags' => $media->tags ?? [], 'is_image' => $media->isImage(),
            'is_audio' => $media->isAudio(), 'is_video' => $media->isVideo(),
            'created_at' => $media->created_at?->toIso8601String(),
            'uploader' => $media->uploader?->name,
            'download_url' => route('media-files.download', $media),
            'stream_url' => $media->isPlayable() ? route('media-files.stream', $media) : null,
            'thumbnail_url' => $media->thumbnail_path !== null ? route('media-files.thumbnail', $media) : null,
            'usages' => array_values($media->pages->map(fn (Page $page): array => [
                'id' => $page->id, 'title' => $page->title, 'trashed' => $page->trashed(),
                'url' => ! $page->trashed() && Gate::allows('update', $page) ? route('pages.edit', $page) : null,
            ])->all()),
            'can' => ['update' => Gate::allows('update', $media), 'delete' => Gate::allows('delete', $media)],
        ];
    }

    /** @return Builder<Media> */
    private function scoped(User $user): Builder
    {
        return Media::query()->when(! $user->can(MediaPolicy::MANAGE_ALL), fn (Builder $query) => $query->where('uploaded_by', $user->id));
    }
}
