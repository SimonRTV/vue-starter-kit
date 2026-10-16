<?php

namespace App\Http\Controllers;

use App\Actions\Pages\PageContent;
use App\Actions\Pages\RecordPageRevision;
use App\Actions\Pages\RestorePageRevision;
use App\Http\Requests\IndexPageHistoryRequest;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;

class PageRevisionController extends Controller
{
    public function index(IndexPageHistoryRequest $request, Page $page, RecordPageRevision $history, PageContent $content): Response|RedirectResponse
    {
        $revisions = $page->revisions()->latest('id')->paginate(10, ['id', 'page_id', 'author_name', 'event', 'is_published', 'created_at'], 'history_page');
        $selected = $request->filled('revision')
            ? $page->revisions()->findOrFail($request->integer('revision'))
            : $page->revisions()->latest('id')->first();
        if ($revisions->currentPage() > $revisions->lastPage()) {
            return to_route('page-revisions.index', [
                'page' => $page, 'history_page' => $revisions->lastPage(), 'revision' => $selected?->id,
            ]);
        }

        $current = $history->content($page);
        Head::title('Historique · '.$page->title);

        return Inertia::render('pages/History', [
            'page' => ['id' => $page->id, 'title' => $page->title, 'is_published' => $page->is_published],
            'revisions' => $revisions,
            'selected' => $selected === null ? null : [
                'id' => $selected->id,
                'author_name' => $selected->author_name,
                'event' => $selected->event,
                'created_at' => $selected->created_at?->toISOString(),
                'content' => $selected->content,
                'template_name' => $this->templateName($selected->content['page_template_id']),
                'body_html' => $content->render($selected->content['body'], $selected->content['body_format']),
            ],
            'current' => [...$current, 'template_name' => $this->templateName($current['page_template_id']), 'body_html' => $content->render($current['body'], $current['body_format'])],
            'canRestore' => Gate::allows('update', $page),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function restore(Page $page, PageRevision $revision, RestorePageRevision $restore): RedirectResponse
    {
        Gate::authorize('view', $page);
        Gate::authorize('update', $page);
        abort_unless($revision->page_id === $page->id, 404);
        $restore->handle($page, $revision);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'La version a été restaurée en brouillon.']);

        return to_route('pages.edit', $page);
    }

    private function templateName(?int $id): string
    {
        return $id === null ? 'Page standard' : (PageTemplate::query()->find($id)->name ?? 'Modèle supprimé');
    }
}
