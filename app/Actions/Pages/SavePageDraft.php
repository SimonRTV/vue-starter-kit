<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Support\Arr;

class SavePageDraft
{
    public function __construct(private UpdatePage $updatePage, private PageContent $content) {}

    /**
     * @param  array{title: string, slug: string, excerpt: string|null, body: string|null, body_format: string, is_published: bool}  $attributes
     */
    public function handle(Page $page, array $attributes): Page
    {
        if (! $page->is_published) {
            return $this->updatePage->handle($page, [...$attributes, 'is_published' => false]);
        }

        if ($attributes['body_format'] === 'html') {
            $attributes['body'] = $this->content->sanitize($attributes['body']);
        }

        $page->update(['draft' => [...($page->draft ?? []), ...Arr::except($attributes, 'is_published')]]);

        return $page->refresh();
    }
}
