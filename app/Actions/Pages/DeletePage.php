<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Support\Facades\DB;

class DeletePage
{
    /**
     * Delete the page.
     */
    public function handle(Page $page): void
    {
        DB::transaction(function () use ($page): void {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
            $locked->attachments()->detach();
            $locked->delete();
        });
    }
}
