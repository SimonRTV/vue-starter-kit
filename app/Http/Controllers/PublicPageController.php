<?php

namespace App\Http\Controllers;

use App\Actions\ApplicationSettings\SeoSettings;
use App\Actions\Media\MediaLibrary;
use App\Actions\Pages\PageContent;
use App\Models\Media;
use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController extends Controller
{
    public function __invoke(Page $page, MediaLibrary $library, SeoSettings $seo): Response
    {
        abort_unless(
            $page->is_published
                && $page->published_at !== null
                && $page->published_at->isPast(),
            404,
        );

        $seo->apply($page);

        return Inertia::render('content/Show', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'body' => $page->body,
                'body_html' => app(PageContent::class)->render($page->body, $page->body_format),
                'published_at' => $page->published_at->toISOString(),
                'updated_at' => $page->updated_at?->toISOString(),
                'attachments' => config('starter.features.media')
                    ? $page->attachments()->where('visibility', 'public')->get()->map(fn (Media $media): array => $library->item($media))
                    : [],
            ],
        ]);
    }
}
