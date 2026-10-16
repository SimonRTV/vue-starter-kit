<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Support\Facades\DB;

class ForceDeletePage
{
    public function handle(Page $page): void
    {
        DB::transaction(function () use ($page): void {
            $locked = Page::onlyTrashed()->lockForUpdate()->findOrFail($page->id);
            $locked->attachments()->detach();
            $locked->forceDelete();
        });
    }
}
