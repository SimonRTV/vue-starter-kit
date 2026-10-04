<?php

namespace App\Http\Controllers;

use App\Actions\ApplicationSettings\SeoSettings;
use App\Models\Page;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(SeoSettings $seo): Response
    {
        $urls = [];
        if ($seo->includesInSitemap()) {
            $urls[] = ['loc' => $seo->publicUrl(), 'lastmod' => null];
        }
        if (config('starter.features.pages')) {
            foreach (Page::query()->where('is_published', true)->where('published_at', '<=', now())->lazyById() as $page) {
                if ($seo->includesInSitemap($page)) {
                    $urls[] = ['loc' => $seo->publicUrl($page), 'lastmod' => $page->updated_at?->toAtomString()];
                }
            }
        }

        return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
