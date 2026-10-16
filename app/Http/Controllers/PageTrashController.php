<?php

namespace App\Http\Controllers;

use App\Actions\Pages\ForceDeletePage;
use App\Actions\Pages\RestorePage;
use App\Http\Requests\IndexPageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;

class PageTrashController extends Controller
{
    public function index(IndexPageRequest $request): Response|RedirectResponse
    {
        Gate::authorize('viewTrash', Page::class);
        $search = $request->filters()['search'];
        $pages = Page::onlyTrashed()
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%')))
            ->orderByDesc('deleted_at')->orderByDesc('id')
            ->paginate(10, ['id', 'title', 'slug', 'deleted_at']);
        if ($pages->currentPage() > $pages->lastPage()) {
            return to_route('page-trash.index', ['search' => $search, 'page' => $pages->lastPage()]);
        }
        Head::title('Corbeille des pages');

        return Inertia::render('pages/Trash', [
            'pages' => $pages,
            'search' => $search,
            'timezone' => config('app.timezone'),
        ]);
    }

    public function restore(Page $page, RestorePage $restore): RedirectResponse
    {
        Gate::authorize('view', $page);
        Gate::authorize('restore', $page);
        $restore->handle($page);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'La page a été restaurée en brouillon.']);

        return to_route('pages.show', $page);
    }

    public function destroy(Page $page, ForceDeletePage $delete): RedirectResponse
    {
        Gate::authorize('view', $page);
        Gate::authorize('forceDelete', $page);
        $delete->handle($page);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'La page et son historique ont été supprimés définitivement.']);

        return to_route('page-trash.index');
    }
}
