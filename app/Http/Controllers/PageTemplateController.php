<?php

namespace App\Http\Controllers;

use App\Actions\Pages\PageTemplates;
use App\Actions\Pages\SavePageTemplate;
use App\Http\Requests\SavePageTemplateRequest;
use App\Models\Page;
use App\Models\PageFieldSet;
use App\Models\PageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;

class PageTemplateController extends Controller
{
    public function index(PageTemplates $registry): Response
    {
        Gate::authorize('manageTemplates', Page::class);
        Head::title('Modèles et champs');

        return Inertia::render('pages/Templates', [
            'templates' => PageTemplate::query()->with('fieldSets')->withCount('pages')->orderBy('name')->get(),
            'fieldSets' => PageFieldSet::query()->withCount('templates')->orderBy('name')->get(),
            'renderers' => $registry->renderers(),
        ]);
    }

    public function store(SavePageTemplateRequest $request, SavePageTemplate $save): RedirectResponse
    {
        $save->handle(new PageTemplate, $request->templateAttributes());

        return to_route('page-templates.index');
    }

    public function update(SavePageTemplateRequest $request, PageTemplate $pageTemplate, SavePageTemplate $save): RedirectResponse
    {
        $save->handle($pageTemplate, $request->templateAttributes());

        return to_route('page-templates.index');
    }

    public function destroy(PageTemplate $pageTemplate): RedirectResponse
    {
        Gate::authorize('manageTemplates', Page::class);
        if ($pageTemplate->pages()->exists() || Page::query()->where('draft->page_template_id', $pageTemplate->id)->exists()) {
            throw ValidationException::withMessages(['template' => 'Ce modèle est utilisé par des pages. Changez leur modèle avant de le supprimer.']);
        }
        $pageTemplate->delete();

        return to_route('page-templates.index');
    }
}
