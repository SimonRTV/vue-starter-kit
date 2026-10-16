<?php

namespace App\Actions\Pages;

use App\Actions\ApplicationSettings\SeoSettings;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ForceDeletePage
{
    public function handle(Page $page): void
    {
        $seoImagePaths = DB::transaction(function () use ($page): array {
            $locked = Page::onlyTrashed()->lockForUpdate()->findOrFail($page->id);
            $locked->attachments()->detach();
            $locked->forceDelete();

            return SeoSettings::imagePaths($locked->seo ?? []);
        });

        Storage::disk('public')->delete(array_values($seoImagePaths));
    }
}
