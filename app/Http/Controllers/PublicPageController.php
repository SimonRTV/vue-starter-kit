<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;

class PublicPageController extends Controller
{
    public function __invoke(Page $page): Response
    {
        abort_unless(
            $page->is_published
                && $page->published_at !== null
                && $page->published_at->isPast(),
            404,
        );

        Head::title($page->title);

        if (filled($page->excerpt)) {
            Head::description($page->excerpt);
        }

        return Inertia::render('content/Show', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'body' => $page->body,
                'published_at' => $page->published_at->toISOString(),
                'updated_at' => $page->updated_at?->toISOString(),
            ],
        ]);
    }
}
