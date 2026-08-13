<?php

namespace App\Actions\Pages;

use App\Models\Page;

class DeletePage
{
    /**
     * Delete the page.
     */
    public function handle(Page $page): void
    {
        $page->delete();
    }
}
