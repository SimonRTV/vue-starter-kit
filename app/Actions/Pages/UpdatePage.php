<?php

namespace App\Actions\Pages;

use App\Models\Page;

class UpdatePage
{
    /**
     * Update a page from validated attributes.
     *
     * @param  array{title: string, slug: string, excerpt: string|null, body: string|null, is_published: bool}  $attributes
     */
    public function handle(Page $page, array $attributes): Page
    {
        $attributes['published_at'] = $attributes['is_published']
            ? ($page->published_at ?? now())
            : null;

        $page->update($attributes);

        return $page->refresh();
    }
}
