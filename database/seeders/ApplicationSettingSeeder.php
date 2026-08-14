<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use Illuminate\Database\Seeder;

class ApplicationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ApplicationSetting::ICON_PATH,
            ApplicationSetting::FULL_LOGO_PATH,
            ApplicationSetting::SIDEBAR_FOOTER_LINKS,
        ] as $key) {
            ApplicationSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => null],
            );
        }
    }
}
