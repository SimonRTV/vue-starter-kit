<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePageFieldSetRequest;
use App\Models\Page;
use App\Models\PageFieldSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PageFieldSetController extends Controller
{
    public function store(SavePageFieldSetRequest $request): RedirectResponse
    {
        PageFieldSet::query()->create($request->validated());

        return to_route('page-templates.index');
    }

    public function update(SavePageFieldSetRequest $request, PageFieldSet $pageFieldSet): RedirectResponse
    {
        $pageFieldSet->update($request->validated());

        return to_route('page-templates.index');
    }

    public function destroy(PageFieldSet $pageFieldSet): RedirectResponse
    {
        Gate::authorize('manageTemplates', Page::class);
        if ($pageFieldSet->templates()->exists()) {
            throw ValidationException::withMessages(['field_set' => 'Ce groupe est utilisé par des modèles. Retirez-le de ces modèles avant de le supprimer.']);
        }
        $pageFieldSet->delete();

        return to_route('page-templates.index');
    }
}
