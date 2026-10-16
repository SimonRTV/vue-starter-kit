<?php

namespace App\Actions\Pages;

use App\Http\Requests\UpdatePageRequest;
use App\Models\Page;
use App\Models\PageRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RestorePageRevision
{
    public function __construct(private SavePageDraft $saveDraft) {}

    public function handle(Page $page, PageRevision $revision): Page
    {
        return DB::transaction(function () use ($page, $revision): Page {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
            abort_unless($revision->page_id === $locked->id, 404);

            $request = new UpdatePageRequest;
            $request->replace([...$revision->content, 'is_published' => false, 'intent' => 'save']);
            $request->setRouteResolver(fn () => request()->route());
            $validator = Validator::make($request->all(), $request->rules());
            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    'revision' => 'Cette version ne peut pas être restaurée : '.$validator->errors()->first(),
                ]);
            }
            $request->setValidator($validator);

            return $this->saveDraft->handle($locked, $request->pageAttributes());
        });
    }
}
