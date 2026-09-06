<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkPageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BulkPageController extends Controller
{
    public function __invoke(BulkPageRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');
        $publish = $request->validated('action') === 'publish';
        DB::transaction(function () use ($ids, $publish): void {
            $pages = Page::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            if ($pages->count() !== count($ids)) {
                throw ValidationException::withMessages(['ids' => 'Une page sélectionnée n’existe plus. Actualisez le tableau.']);
            }
            foreach ($pages as $page) {
                Gate::authorize('update', $page);
            }
            foreach ($pages as $page) {
                $page->update([
                    'is_published' => $publish,
                    'published_at' => $publish ? ($page->published_at ?? now()) : null,
                ]);
            }
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Les pages sélectionnées ont été mises à jour.']);

        return back();
    }
}
