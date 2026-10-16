<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Support\Facades\DB;

class RestorePage
{
    public function handle(Page $page): Page
    {
        return DB::transaction(function () use ($page): Page {
            $locked = Page::onlyTrashed()->lockForUpdate()->findOrFail($page->id);
            app(RecordPageRevision::class)->handle($locked, 'baseline');
            $locked->is_published = false;
            $locked->published_at = null;
            $locked->restore();
            app(RecordPageRevision::class)->handle($locked, 'recovered');

            return $locked;
        });
    }
}
