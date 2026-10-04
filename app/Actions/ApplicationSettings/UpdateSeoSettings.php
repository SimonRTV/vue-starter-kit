<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use App\Models\Page;

class UpdateSeoSettings
{
    /** @param array<string, string> $attributes */
    public function handle(string $target, array $attributes, ?Page $page = null): void
    {
        if ($page !== null) {
            $page->update(['seo' => $attributes]);

            return;
        }

        ApplicationSetting::query()->updateOrCreate(
            ['key' => 'seo.'.$target],
            ['value' => json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
    }
}
