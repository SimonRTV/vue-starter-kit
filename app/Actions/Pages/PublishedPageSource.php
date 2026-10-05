<?php

namespace App\Actions\Pages;

use App\Models\Media;
use App\Models\Page;
use Illuminate\Database\Eloquent\Relations\Relation;

class PublishedPageSource extends TemplateDataSource
{
    public function orders(): array
    {
        return ['published_at' => 'Publication', 'title' => 'Titre', 'updated_at' => 'Modification'];
    }

    public function items(Page $page, int $limit, string $orderBy, string $direction): array
    {
        $query = Page::query()->select(['id', 'title', 'slug', 'excerpt'])
            ->where('is_published', true)->where('published_at', '<=', now())->whereKeyNot($page->id)
            ->orderBy(array_key_exists($orderBy, $this->orders()) ? $orderBy : 'published_at', $direction === 'asc' ? 'asc' : 'desc')
            ->orderBy('id')->limit(max(1, min(100, $limit)));

        if (config('starter.features.media')) {
            $query->with(['attachments' => function (Relation $relation): void {
                $relation->getQuery()->where('visibility', 'public')->where('mime_type', 'like', 'image/%')->orderBy('media.id')->limit(1);
            }]);
        }

        return array_values($query->get()->map(function (Page $item): array {
            /** @var Media|null $image */
            $image = config('starter.features.media') ? $item->attachments->first() : null;

            return [
                'title' => $item->title,
                'url' => route('content.show', $item->slug),
                'excerpt' => $item->excerpt,
                'image' => $image ? route($image->thumbnail_path !== null ? 'media-files.thumbnail' : 'media-files.download', $image) : null,
            ];
        })->all());
    }
}
