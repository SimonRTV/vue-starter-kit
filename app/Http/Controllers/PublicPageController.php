<?php

namespace App\Http\Controllers;

use App\Actions\ApplicationSettings\SeoSettings;
use App\Actions\Media\MediaLibrary;
use App\Actions\Pages\PageContent;
use App\Actions\Pages\PageTemplates;
use App\Actions\Pages\TemplateFields;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;

class PublicPageController extends Controller
{
    public function __invoke(Page $page, MediaLibrary $library, SeoSettings $seo, PageTemplates $templates, TemplateFields $fields): Response
    {
        abort_unless(
            $page->is_published
                && $page->published_at !== null
                && $page->published_at->isPast(),
            404,
        );

        $seo->apply($page);

        return $this->render($page, $library, $templates, $fields);
    }

    public function preview(Page $page, MediaLibrary $library, SeoSettings $seo, PageTemplates $templates, TemplateFields $fields): Response
    {
        Gate::authorize('view', $page);

        $previewPage = clone $page;
        $previewPage->fill($page->draft ?? []);
        $seo->apply($previewPage);
        Head::robots('none');

        return $this->render($previewPage, $library, $templates, $fields)->with('preview', [
            'id' => $page->id,
            'canUpdate' => Gate::allows('update', $page),
            'isPublished' => $page->is_published && $page->published_at?->isPast(),
        ]);
    }

    private function render(Page $page, MediaLibrary $library, PageTemplates $templates, TemplateFields $fields): Response
    {
        $page->load('template.fieldSets');

        return Inertia::render($templates->component($page->template) ?? 'content/Show', [
            ...$fields->publicProps($page),
            'preview' => null,
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'body' => $page->body,
                'body_html' => app(PageContent::class)->render($page->body, $page->body_format),
                'published_at' => $page->published_at?->toISOString(),
                'updated_at' => $page->updated_at?->toISOString(),
                'attachments' => config('starter.features.media')
                    ? $page->attachments()->where('visibility', 'public')->get()->map(fn (Media $media): array => $library->item($media))
                    : [],
            ],
        ]);
    }
}
