<?php

namespace App\Http\Controllers;

use App\Actions\Pages\CreatePage;
use App\Actions\Pages\DeletePage;
use App\Actions\Pages\ListPages;
use App\Actions\Pages\UpdatePage;
use App\Http\Requests\IndexPageRequest;
use App\Http\Requests\StorePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(
        private ListPages $listPages,
        private CreatePage $createPage,
        private UpdatePage $updatePage,
        private DeletePage $deletePage,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexPageRequest $request): Response|RedirectResponse
    {
        $filters = $request->filters();
        $pages = $this->listPages
            ->handle($filters)
            ->through(fn (Page $page): array => $this->summary($page));

        if ($pages->currentPage() > $pages->lastPage()) {
            return to_route('pages.index', $request->canonicalQuery($pages->lastPage()));
        }

        return Inertia::render('pages/Index', [
            'pages' => $pages,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        Gate::authorize('create', Page::class);

        return Inertia::render('pages/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePageRequest $request): RedirectResponse
    {
        $page = $this->createPage->handle($request->pageAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page created.')]);

        return to_route('pages.show', $page);
    }

    /**
     * Display the specified resource.
     */
    public function show(Page $page): Response
    {
        Gate::authorize('view', $page);

        return Inertia::render('pages/Show', ['page' => $this->detail($page)]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Page $page): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('pages/Edit', ['page' => $this->detail($page)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        $this->updatePage->handle($page, $request->pageAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page updated.')]);

        return to_route('pages.show', $page);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Page $page): RedirectResponse
    {
        Gate::authorize('delete', $page);

        $this->deletePage->handle($page);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page deleted.')]);

        return to_route('pages.index');
    }

    /**
     * @return array{id: int, title: string, slug: string, is_published: bool, status: string, published_at: string|null, updated_at: string|null}
     */
    private function summary(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'is_published' => $page->is_published,
            'status' => $page->is_published ? 'published' : 'draft',
            'published_at' => $page->published_at?->toISOString(),
            'updated_at' => $page->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array{id: int, title: string, slug: string, excerpt: string|null, body: string|null, is_published: bool, status: string, published_at: string|null, created_at: string|null, updated_at: string|null}
     */
    private function detail(Page $page): array
    {
        return [
            ...$this->summary($page),
            'excerpt' => $page->excerpt,
            'body' => $page->body,
            'created_at' => $page->created_at?->toISOString(),
        ];
    }
}
