<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteApplicationLogo
{
    public function handle(string $settingKey): void
    {
        $logoPath = DB::transaction(function () use ($settingKey): ?string {
            $applicationSetting = ApplicationSetting::query()
                ->where('key', $settingKey)
                ->lockForUpdate()
                ->first();

            if ($applicationSetting === null) {
                return null;
            }

            $logoPath = $applicationSetting->value;
            $applicationSetting->update(['value' => null]);

            return is_string($logoPath) ? $logoPath : null;
        });

        if ($logoPath !== null) {
            Storage::disk('public')->delete($logoPath);
        }
    }
}
