<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;

class UpdateFrontendNavigation
{
    /**
     * @param  list<array{
     *     type: 'link'|'group',
     *     label: string,
     *     url: string|null,
     *     children: list<array{label: string, url: string, description: string}>
     * }>  $items
     */
    public function handle(array $items): ApplicationSetting
    {
        return ApplicationSetting::query()->updateOrCreate(
            ['key' => ApplicationSetting::FRONTEND_NAVIGATION],
            [
                'value' => json_encode(
                    $items,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
            ],
        );
    }
}
