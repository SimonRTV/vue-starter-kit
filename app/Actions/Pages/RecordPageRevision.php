<?php

namespace App\Actions\Pages;

use App\Models\Page;
use App\Models\PageRevision;
use Illuminate\Support\Arr;

/** @phpstan-import-type Content from PageRevision */
class RecordPageRevision
{
    public function handle(Page $page, string $event = 'saved'): void
    {
        $latest = $page->revisions()->latest('id')->first();
        if ($latest === null && $page->draft !== null) {
            $published = clone $page;
            $published->draft = null;
            $this->handle($published, 'baseline');
        }

        $content = $this->content($page);
        $published = $page->is_published && $page->draft === null;
        if ($latest !== null && $latest->content === $content && $latest->is_published === $published) {
            return;
        }

        $page->revisions()->create([
            'content' => $content,
            'is_published' => $published,
            'event' => $event,
            'user_id' => $event === 'baseline' ? null : auth()->id(),
            'author_name' => $event === 'baseline' ? null : auth()->user()?->name,
            'created_at' => $event === 'baseline' ? ($page->updated_at ?? now()) : now(),
        ]);
    }

    /** @return Content */
    public function content(Page $page): array
    {
        $editable = clone $page;
        $editable->fill(Arr::only($page->draft ?? [], PageRevision::CONTENT_FIELDS));

        return [
            'title' => $editable->title,
            'slug' => $editable->slug,
            'excerpt' => $editable->excerpt,
            'body' => $editable->body,
            'body_format' => $editable->body_format,
            'page_template_id' => $editable->page_template_id,
            'template_fields' => $editable->template_fields,
        ];
    }
}
