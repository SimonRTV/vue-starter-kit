<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SavePageDraft
{
    public function __construct(private UpdatePage $updatePage, private PageContent $content) {}

    /**
     * @param  array{title: string, slug: string, excerpt: string|null, body: string|null, body_format: string, is_published: bool}  $attributes
     */
    public function handle(Page $page, array $attributes): Page
    {
        return DB::transaction(function () use ($page, $attributes): Page {
            $page = Page::query()->lockForUpdate()->findOrFail($page->id);
            app(RecordPageRevision::class)->handle($page, 'baseline');
            if (! $page->is_published) {
                return $this->updatePage->handle($page, [...$attributes, 'is_published' => false]);
            }

            if ($attributes['body_format'] === 'html') {
                $attributes['body'] = $this->content->sanitize($attributes['body']);
            }

            $page->update(['draft' => [...($page->draft ?? []), ...Arr::except($attributes, 'is_published')]]);

            app(RecordPageRevision::class)->handle($page);

            return $page->refresh();
        });
    }
}
