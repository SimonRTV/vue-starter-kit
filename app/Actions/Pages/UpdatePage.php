<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Support\Facades\DB;

class UpdatePage
{
    /**
     * Update a page from validated attributes.
     *
     * @param  array{title: string, slug: string, excerpt: string|null, body: string|null, body_format?: string, is_published: bool}  $attributes
     */
    public function handle(Page $page, array $attributes): Page
    {
        return DB::transaction(function () use ($page, $attributes): Page {
            $page = Page::query()->lockForUpdate()->findOrFail($page->id);
            app(RecordPageRevision::class)->handle($page, 'baseline');
            if (($attributes['body_format'] ?? 'text') === 'html') {
                $attributes['body'] = app(PageContent::class)->sanitize($attributes['body']);
            }

            $attributes['published_at'] = $attributes['is_published']
                ? ($page->published_at ?? now())
                : null;

            $page->update([...$attributes, 'draft' => null]);

            app(RecordPageRevision::class)->handle($page);

            return $page->refresh();
        });
    }
}
