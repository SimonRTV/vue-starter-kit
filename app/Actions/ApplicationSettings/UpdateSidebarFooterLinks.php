<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;

class UpdateSidebarFooterLinks
{
    /**
     * @param  list<array{title: string, url: string}>  $links
     */
    public function handle(array $links): ApplicationSetting
    {
        return ApplicationSetting::query()->updateOrCreate(
            ['key' => ApplicationSetting::SIDEBAR_FOOTER_LINKS],
            [
                'value' => json_encode(
                    $links,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
            ],
        );
    }
}
