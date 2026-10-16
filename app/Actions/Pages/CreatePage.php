<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePage
{
    /**
     * Create a page from validated attributes.
     *
     * @param  array{title: string, slug: string, excerpt: string|null, body: string|null, body_format?: string, is_published: bool}  $attributes
     */
    public function handle(array $attributes): Page
    {
        if (($attributes['body_format'] ?? 'text') === 'html') {
            $attributes['body'] = app(PageContent::class)->sanitize($attributes['body']);
        }

        $attributes['published_at'] = $attributes['is_published'] ? now() : null;

        $baseSlug = $attributes['slug'];
        $suffixNumber = 1;

        do {
            $suffix = $suffixNumber === 1 ? '' : '-'.$suffixNumber;
            $attributes['slug'] = Str::substr($baseSlug, 0, 255 - Str::length($suffix)).$suffix;

            $page = DB::transaction(function () use ($attributes): Page {
                $page = Page::withTrashed()->firstOrCreate(['slug' => $attributes['slug']], $attributes);
                if ($page->wasRecentlyCreated) {
                    app(RecordPageRevision::class)->handle($page, 'created');
                }

                return $page;
            });
            $suffixNumber++;
        } while (! $page->wasRecentlyCreated);

        return $page;
    }
}
