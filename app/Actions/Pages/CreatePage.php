<?php

namespace App\Actions\Pages;

use App\Models\Page;

class CreatePage
{
    /**
     * Create a page from validated attributes.
     *
     * @param  array{title: string, slug: string, excerpt: string|null, body: string|null, is_published: bool}  $attributes
     */
    public function handle(array $attributes): Page
    {
        $attributes['published_at'] = $attributes['is_published'] ? now() : null;

        return Page::create($attributes);
    }
}
